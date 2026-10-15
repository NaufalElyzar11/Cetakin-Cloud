<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submission_attempts', function (Blueprint $table): void {
            $table->string('object_driver', 8)->default('local');
            $table->text('object_namespace')->default('local');
            $table->string('object_endpoint', 64)->default(str_repeat('0', 64));
        });
        DB::statement("alter table submission_attempts add constraint exact_object_store_identity check (object_endpoint ~ '^[a-f0-9]{64}$' and ((object_driver = 'local' and object_namespace = 'local' and object_endpoint = repeat('0', 64)) or (object_driver = 's3' and object_namespace ~ '^[a-z0-9][a-z0-9-]{2,62}/[a-z0-9/-]+$')))");
        Schema::create('submission_object_guards', function (Blueprint $table): void {
            $table->uuid('attempt_id')->primary();
            $table->foreign('attempt_id')->references('id')->on('submission_attempts');
            $table->string('state', 16)->default('OPEN');
            $table->jsonb('disposition')->nullable();
            $table->timestampTz('disposed_at')->nullable();
            $table->uuid('deletion_claim')->nullable()->unique();
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
        });
        DB::statement("alter table submission_object_guards add constraint exact_object_disposition check ((state = 'OPEN' and disposition is null and disposed_at is null and deletion_claim is null and claimed_at is null and deleted_at is null) or (state = 'UNUSED' and jsonb_typeof(disposition) = 'object' and disposed_at is not null and deletion_claim is null and claimed_at is null and deleted_at is null) or (state = 'DELETE_CLAIMED' and jsonb_typeof(disposition) = 'object' and disposed_at is not null and deletion_claim is not null and claimed_at is not null and deleted_at is null) or (state = 'DELETED' and jsonb_typeof(disposition) = 'object' and disposed_at is not null and deletion_claim is not null and claimed_at is not null and deleted_at is not null))");
        Schema::create('submission_object_publications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('attempt_id');
            $table->foreign('attempt_id')->references('attempt_id')->on('submission_object_guards');
            $table->string('outcome', 16)->default('IN_FLIGHT');
            $table->timestampTz('started_at');
            $table->timestampTz('settled_at')->nullable();
            $table->index(['attempt_id', 'outcome']);
        });
        DB::statement("alter table submission_object_publications add constraint exact_publication_outcome check ((outcome = 'IN_FLIGHT' and settled_at is null) or (outcome in ('SUCCEEDED', 'REJECTED', 'UNKNOWN') and settled_at is not null))");
        DB::statement("insert into submission_object_guards(attempt_id, state) select id, 'OPEN' from submission_attempts");
        // CET-006 has no settled writer evidence. A pending legacy intent may
        // still have a late writer, so backfill uncertainty, never guess unused.
        DB::statement("insert into submission_object_publications(id, attempt_id, outcome, started_at, settled_at) select gen_random_uuid(), id, 'UNKNOWN', created_at, now() from submission_attempts where request_id is null");
        DB::unprepared(<<<'SQL'
create or replace function exact_object_initial_guard() returns trigger language plpgsql as $$
begin
    insert into submission_object_guards(attempt_id) values(NEW.id);
    return NEW;
end;
$$;
create trigger exact_object_guard_after_intent after insert on submission_attempts for each row execute function exact_object_initial_guard();
create or replace function exact_object_association_guard() returns trigger language plpgsql as $$
begin
    perform 1 from submission_object_guards where attempt_id = NEW.attempt_id and state = 'OPEN' for update;
    if not found then raise exception 'Exact content was claimed unused; association is forbidden' using errcode = '23514'; end if;
    return NEW;
end;
$$;
create trigger exact_object_association before insert on print_requests for each row execute function exact_object_association_guard();
create or replace function exact_object_publication_guard() returns trigger language plpgsql as $$
begin
    if TG_OP = 'INSERT' then
        perform 1 from submission_object_guards where attempt_id = NEW.attempt_id and state = 'OPEN' for update;
        if not found then raise exception 'Sealed intent cannot start publication' using errcode = '23514'; end if;
        if NEW.outcome <> 'IN_FLIGHT' then raise exception 'Publication must start in flight' using errcode = '23514'; end if;
        return NEW;
    end if;
    if TG_OP = 'DELETE' or OLD.outcome <> 'IN_FLIGHT' or NEW.outcome = 'IN_FLIGHT'
       or (to_jsonb(OLD) - 'outcome' - 'settled_at') is distinct from (to_jsonb(NEW) - 'outcome' - 'settled_at') then
       raise exception 'Publication provenance/outcome is retained' using errcode = '23514';
    end if;
    perform 1 from submission_object_guards where attempt_id = NEW.attempt_id for update;
    return NEW;
end;
$$;
create trigger exact_object_publication before insert or update or delete on submission_object_publications for each row execute function exact_object_publication_guard();
create or replace function exact_object_disposition_guard() returns trigger language plpgsql as $$
declare confirmed uuid;
begin
    if TG_OP = 'DELETE' then raise exception 'Object coordination history is retained' using errcode = '23514'; end if;
    if NEW.attempt_id is distinct from OLD.attempt_id then raise exception 'Intent identity is immutable' using errcode = '23514'; end if;
    select request_id into confirmed from submission_attempts where id = NEW.attempt_id;
    if OLD.state = 'OPEN' and NEW.state = 'UNUSED' then
        if confirmed is not null or exists(select 1 from print_requests where attempt_id = NEW.attempt_id)
           or exists(select 1 from design_file_versions where attempt_id = NEW.attempt_id)
           or exists(select 1 from submission_object_publications where attempt_id = NEW.attempt_id and outcome in ('IN_FLIGHT', 'UNKNOWN')) then
            raise exception 'Confirmed, in-flight or ambiguous content stays protected' using errcode = '23514';
        end if;
        if NEW.disposition->>'kind' is distinct from 'DISPOSABLE_SYNTHETIC' or coalesce(NEW.disposition->>'source', '') = ''
           or coalesce(NEW.disposition->>'reason', '') = '' or coalesce(NEW.disposition->>'scope', '') = '' then
            raise exception 'Explicit synthetic disposition evidence required' using errcode = '23514';
        end if;
    elsif OLD.state = 'UNUSED' and NEW.state = 'DELETE_CLAIMED' then
        if NEW.disposition is distinct from OLD.disposition or NEW.disposed_at is distinct from OLD.disposed_at then
            raise exception 'Disposition evidence is immutable' using errcode = '23514';
        end if;
    elsif OLD.state = 'DELETE_CLAIMED' and NEW.state = 'DELETED' then
        if (to_jsonb(NEW) - 'state' - 'deleted_at') is distinct from (to_jsonb(OLD) - 'state' - 'deleted_at') then
            raise exception 'Deletion claim and evidence are immutable' using errcode = '23514';
        end if;
    else raise exception 'Invalid exact object guard transition' using errcode = '23514';
    end if;
    return NEW;
end;
$$;
create trigger exact_object_disposition before update or delete on submission_object_guards for each row execute function exact_object_disposition_guard();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('drop trigger if exists exact_object_association on print_requests; drop trigger if exists exact_object_guard_after_intent on submission_attempts');
        Schema::dropIfExists('submission_object_publications');
        Schema::dropIfExists('submission_object_guards');
        DB::unprepared('drop function if exists exact_object_disposition_guard(), exact_object_publication_guard(), exact_object_association_guard(), exact_object_initial_guard()');
        DB::statement('alter table submission_attempts drop constraint exact_object_store_identity');
        Schema::table('submission_attempts', fn (Blueprint $table) => $table->dropColumn(['object_driver', 'object_namespace', 'object_endpoint']));
    }
};
