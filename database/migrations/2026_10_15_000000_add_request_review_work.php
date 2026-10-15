<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_review_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_id');
            $table->foreign('request_id')->references('id')->on('print_requests');
            $table->bigInteger('revision');
            $table->string('kind', 16);
            $table->foreignId('actor_id')->constrained('users');
            $table->foreignId('actor_grant_id')->constrained('staff_responsibility_grants');
            $table->string('basis_digest', 64);
            $table->jsonb('basis');
            foreach (['complete', 'material_available', 'feasible', 'sufficient', 'files_available'] as $field) {
                $table->boolean($field)->nullable();
            }
            $table->timestampTz('files_checked_at')->nullable();
            $table->text('findings')->nullable();
            $table->string('reason', 1000)->nullable();
            $table->string('customer_reason', 1000)->nullable();
            $table->foreignId('responsible_grant_id')->nullable()->constrained('staff_responsibility_grants');
            $table->string('next_action', 500)->nullable();
            $table->string('work_disposition', 16);
            $table->timestampTz('occurred_at');
            $table->unique(['request_id', 'revision']);
            $table->unique(['id', 'request_id', 'revision']);
        });
        Schema::create('request_review_work', function (Blueprint $table): void {
            $table->uuid('request_id')->primary();
            $table->foreign('request_id')->references('id')->on('print_requests');
            $table->bigInteger('revision')->default(0);
            $table->unsignedBigInteger('event_id')->nullable()->unique();
        });
        DB::statement('insert into request_review_work(request_id, revision) select id, 0 from print_requests');
        DB::statement('alter table request_review_work add constraint request_review_current_event foreign key(event_id, request_id, revision) references request_review_events(id, request_id, revision) deferrable initially deferred');
        DB::statement('alter table request_review_work add constraint request_work_revision check ((revision = 0 and event_id is null) or (revision > 0 and event_id is not null))');
        DB::statement("create unique index request_decline_once on request_review_events(request_id) where kind = 'DECLINE'");
        DB::statement("alter table request_review_events add constraint request_review_facts check (revision > 0 and basis_digest ~ '^[0-9a-f]{64}$' and jsonb_typeof(basis) = 'object' and kind in ('ASSIGN','REVIEW','DECLINE') and work_disposition in ('ACTIONABLE','WAITING','BLOCKED','TERMINAL') and (case when kind = 'REVIEW' then complete is not null and material_available is not null and feasible is not null and sufficient is not null and files_available is not null and files_checked_at is not null and coalesce(char_length(btrim(findings)), 0) between 1 and 4000 and (not sufficient or (complete and material_available and feasible and files_available)) else complete is null and material_available is null and feasible is null and sufficient is null and files_available is null and files_checked_at is null and findings is null end) and (case when kind = 'DECLINE' then responsible_grant_id is null and next_action is null and work_disposition = 'TERMINAL' and coalesce(char_length(btrim(reason)), 0) between 1 and 1000 and coalesce(char_length(btrim(customer_reason)), 0) between 1 and 1000 else work_disposition <> 'TERMINAL' and coalesce(char_length(btrim(next_action)), 0) between 1 and 500 and customer_reason is null and (kind <> 'ASSIGN' or coalesce(char_length(btrim(reason)), 0) between 1 and 1000) end))");
        DB::statement('alter table print_requests drop constraint print_request_specification');
        DB::statement("alter table print_requests add constraint print_request_specification check (material = 'PETG' and color in ('orange', 'sky blue', 'white', 'black') and quantity > 0 and status in ('SUBMITTED', 'DECLINED') and (notes is null or char_length(notes) <= 4000))");
        DB::unprepared(<<<'SQL'
create or replace function create_request_review_work() returns trigger language plpgsql as $$
begin
    if NEW.status <> 'SUBMITTED' then raise exception 'New requests begin submitted, never pre-declined' using errcode = '23514'; end if;
    insert into request_review_work(request_id, revision) values(NEW.id, 0); return NEW;
end;
$$;
create trigger request_review_work_created after insert on print_requests for each row execute function create_request_review_work();
create trigger request_review_events_retained before update or delete on request_review_events for each row execute function intake_immutable();
create or replace function guard_request_review_event() returns trigger language plpgsql as $$
declare
    p print_requests%rowtype;
    w request_review_work%rowtype;
    a submission_attempts%rowtype;
    responsible bigint;
begin
    perform 1 from access_control where id = 1 for update;
    select * into p from print_requests where id = NEW.request_id for update;
    select * into w from request_review_work where request_id = NEW.request_id for update;
    if p.status <> 'SUBMITTED' or NEW.revision <> w.revision + 1 then
       raise exception 'Review work is terminal or stale' using errcode = '23514';
    end if;
    if not exists(select 1 from staff_responsibility_grants where id = NEW.actor_grant_id and user_id = NEW.actor_id and responsibility = 'owner' and revoked_at is null) then
       raise exception 'Current Owner authority is required' using errcode = '23514';
    end if;
    select responsible_grant_id into responsible from request_review_events where id = w.event_id;
    if NEW.kind in ('REVIEW','DECLINE') and responsible is distinct from NEW.actor_grant_id then
       raise exception 'Current explicit review assignment is required' using errcode = '23514';
    end if;
    if NEW.responsible_grant_id is not null and not exists(select 1 from staff_responsibility_grants where id = NEW.responsible_grant_id and responsibility = 'owner' and revoked_at is null) then
       raise exception 'Review assignee needs current Owner authority' using errcode = '23514';
    end if;
    select * into a from submission_attempts where id = p.attempt_id;
    if NEW.basis_digest is distinct from a.payload_digest or NEW.basis is distinct from jsonb_build_object('specification', a.specification, 'files', a.manifest) then
       raise exception 'Review must retain the exact submission basis' using errcode = '23514';
    end if;
    return NEW;
end;
$$;
create trigger request_review_event_guard before insert on request_review_events for each row execute function guard_request_review_event();
create or replace function guard_request_review_work() returns trigger language plpgsql as $$
begin
    if TG_OP = 'DELETE' or NEW.request_id is distinct from OLD.request_id or NEW.revision <> OLD.revision + 1 or
       not exists(select 1 from request_review_events where id = NEW.event_id and request_id = NEW.request_id and revision = NEW.revision) then
       raise exception 'Current work advances only with its retained event' using errcode = '23514';
    end if;
    return NEW;
end;
$$;
create trigger request_review_work_guard before update or delete on request_review_work for each row execute function guard_request_review_work();
create or replace function complete_request_review_event() returns trigger language plpgsql as $$
begin
    if not exists(select 1 from request_review_work where request_id = NEW.request_id and revision >= NEW.revision) or
       (NEW.kind = 'DECLINE' and not exists(select 1 from print_requests where id = NEW.request_id and status = 'DECLINED')) then
       raise exception 'Review event and resulting work/state must commit together' using errcode = '23514';
    end if;
    return null;
end;
$$;
create constraint trigger complete_request_review after insert on request_review_events deferrable initially deferred for each row execute function complete_request_review_event();
drop trigger immutable_request on print_requests;
create or replace function guard_reviewed_request_state() returns trigger language plpgsql as $$
begin
    if TG_OP = 'DELETE' or OLD.status <> 'SUBMITTED' or NEW.status <> 'DECLINED' or
       (to_jsonb(NEW) - 'status') is distinct from (to_jsonb(OLD) - 'status') or
       not exists(select 1 from request_review_work w join request_review_events e on e.id = w.event_id where w.request_id = OLD.id and e.kind = 'DECLINE') then
       raise exception 'Fixed submission is retained; only attributed decline may change state' using errcode = '23514';
    end if;
    return NEW;
end;
$$;
create trigger immutable_request before update or delete on print_requests for each row execute function guard_reviewed_request_state();
SQL);
    }

    public function down(): void
    {
        // An applied decline/history cannot safely be erased by a down migration.
        if (DB::table('request_review_events')->exists()) {
            throw new RuntimeException('Review history exists; use a reviewed forward migration.');
        }
        DB::unprepared('drop trigger immutable_request on print_requests; drop trigger request_review_work_created on print_requests; create trigger immutable_request before update or delete on print_requests for each row execute function intake_immutable()');
        Schema::dropIfExists('request_review_work');
        Schema::dropIfExists('request_review_events');
        DB::statement('alter table print_requests drop constraint print_request_specification');
        DB::statement("alter table print_requests add constraint print_request_specification check (material = 'PETG' and color in ('orange', 'sky blue', 'white', 'black') and quantity > 0 and status = 'SUBMITTED' and (notes is null or char_length(notes) <= 4000))");
        DB::unprepared('drop function create_request_review_work(), guard_request_review_event(), guard_request_review_work(), complete_request_review_event(), guard_reviewed_request_state()');
    }
};
