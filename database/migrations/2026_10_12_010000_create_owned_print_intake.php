<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('actor_id')->constrained('users');
            $table->foreignId('customer_id')->constrained();
            $table->uuid('operation_id');
            $table->string('payload_digest', 64);
            $table->jsonb('specification');
            $table->jsonb('manifest');
            $table->timestampTz('created_at');
            $table->uuid('request_id')->nullable()->unique();
            $table->timestampTz('confirmed_at')->nullable();
            $table->unique(['actor_id', 'operation_id']);
            $table->unique(['id', 'customer_id', 'actor_id']);
            $table->foreign(['actor_id', 'customer_id'])->references(['user_id', 'customer_id'])->on('customer_user');
        });
        DB::statement("alter table submission_attempts add constraint submission_attempt_integrity check (payload_digest ~ '^[0-9a-f]{64}$' and jsonb_typeof(specification) = 'object' and jsonb_typeof(manifest) = 'array' and jsonb_array_length(manifest) between 1 and 5 and ((request_id is null and confirmed_at is null) or (request_id is not null and confirmed_at is not null)))");
        Schema::create('print_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('attempt_id')->unique();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('submitted_by')->constrained('users');
            $table->string('material', 8);
            $table->string('color', 16);
            $table->bigInteger('quantity');
            $table->text('notes')->nullable();
            $table->string('status', 16);
            $table->timestampTz('submitted_at');
            $table->unique(['id', 'attempt_id', 'customer_id', 'submitted_by']);
            $table->foreign(['attempt_id', 'customer_id', 'submitted_by'])->references(['id', 'customer_id', 'actor_id'])->on('submission_attempts');
        });
        DB::statement("alter table print_requests add constraint print_request_specification check (material = 'PETG' and color in ('orange', 'sky blue', 'white', 'black') and quantity > 0 and status = 'SUBMITTED' and (notes is null or char_length(notes) <= 4000))");
        Schema::create('design_file_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('request_id');
            $table->uuid('attempt_id');
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('submitted_by')->constrained('users');
            $table->smallInteger('position');
            $table->string('original_name', 200);
            $table->string('format', 3);
            $table->integer('size_bytes');
            $table->string('digest', 64);
            $table->timestampTz('submitted_at');
            $table->unique(['request_id', 'position']);
            $table->foreign(['request_id', 'attempt_id', 'customer_id', 'submitted_by'])->references(['id', 'attempt_id', 'customer_id', 'submitted_by'])->on('print_requests');
        });
        DB::statement("alter table design_file_versions add constraint design_file_integrity check (position between 1 and 5 and format in ('STL', '3MF') and size_bytes between 1 and 26214400 and digest ~ '^[0-9a-f]{64}$' and octet_length(original_name) between 1 and 200)");
        DB::statement('alter table submission_attempts add constraint submission_result_request foreign key (request_id, id, customer_id, actor_id) references print_requests(id, attempt_id, customer_id, submitted_by) deferrable initially deferred');
        DB::unprepared(<<<'SQL'
create or replace function intake_immutable() returns trigger language plpgsql as $$
begin
    raise exception 'Confirmed submission and exact design provenance cannot be rewritten' using errcode = '23514';
end;
$$;
create trigger immutable_request before update or delete on print_requests for each row execute function intake_immutable();
create trigger immutable_design_version before update or delete on design_file_versions for each row execute function intake_immutable();
create or replace function intake_attempt_guard() returns trigger language plpgsql as $$
begin
    if TG_OP = 'DELETE' then
        raise exception 'Durable submission intent must be retained' using errcode = '23514';
    end if;
    if (to_jsonb(NEW) - 'request_id' - 'confirmed_at') is distinct from (to_jsonb(OLD) - 'request_id' - 'confirmed_at')
       or OLD.request_id is not null or NEW.request_id is null or NEW.confirmed_at is null then
        raise exception 'Attempt payload and confirmed result are immutable' using errcode = '23514';
    end if;
    return NEW;
end;
$$;
create trigger immutable_submission_attempt before update or delete on submission_attempts for each row execute function intake_attempt_guard();
create or replace function intake_current_owner_guard() returns trigger language plpgsql as $$
declare
    actor bigint;
begin
    if TG_TABLE_NAME = 'submission_attempts' then actor := NEW.actor_id;
    else actor := NEW.submitted_by;
    end if;
    perform 1 from customer_user where user_id = actor and customer_id = NEW.customer_id and revoked_at is null for share;
    if not found then
       raise exception 'Submission requires a current customer relationship' using errcode = '23514';
    end if;
    return NEW;
end;
$$;
create trigger attempt_current_owner before insert on submission_attempts for each row execute function intake_current_owner_guard();
create trigger request_current_owner before insert on print_requests for each row execute function intake_current_owner_guard();
create or replace function intake_complete_guard() returns trigger language plpgsql as $$
declare
    attempt submission_attempts%rowtype;
    amount integer;
    bytes bigint;
    correct integer;
begin
    select * into attempt from submission_attempts where id = NEW.attempt_id;
    select count(*), coalesce(sum(size_bytes), 0) into amount, bytes from design_file_versions where request_id = NEW.id;
    select count(*) into correct from design_file_versions v
       where v.request_id = NEW.id and v.id::text = (attempt.manifest->(v.position - 1)->>'id')
       and v.original_name = (attempt.manifest->(v.position - 1)->>'name')
       and v.format = (attempt.manifest->(v.position - 1)->>'format')
       and v.size_bytes = (attempt.manifest->(v.position - 1)->>'size')::integer
       and v.digest = (attempt.manifest->(v.position - 1)->>'digest');
    if attempt.request_id is distinct from NEW.id or amount < 1 or amount > 5 or bytes > 78643200
       or amount <> jsonb_array_length(attempt.manifest) or correct <> amount
       or NEW.material is distinct from (attempt.specification->>'material')
       or NEW.color is distinct from (attempt.specification->>'color')
       or NEW.quantity::text is distinct from (attempt.specification->>'quantity')
       or NEW.notes is distinct from (attempt.specification->>'notes') then
        raise exception 'Confirmation requires exact complete file associations and fixed specification' using errcode = '23514';
    end if;
    return null;
end;
$$;
create constraint trigger complete_print_request after insert on print_requests deferrable initially deferred for each row execute function intake_complete_guard();
create or replace function intake_file_insert_guard() returns trigger language plpgsql as $$
begin
    if exists(select 1 from submission_attempts where id = NEW.attempt_id and request_id is not null) then
       raise exception 'Confirmed exact selection cannot gain silently added versions' using errcode = '23514';
    end if;
    return NEW;
end;
$$;
create trigger confirmed_file_insert_guard before insert on design_file_versions for each row execute function intake_file_insert_guard();
SQL);
    }

    public function down(): void
    {
        Schema::table('submission_attempts', fn (Blueprint $table) => $table->dropForeign('submission_result_request'));
        Schema::dropIfExists('design_file_versions');
        Schema::dropIfExists('print_requests');
        Schema::dropIfExists('submission_attempts');
        DB::unprepared('drop function if exists intake_file_insert_guard(), intake_complete_guard(), intake_attempt_guard(), intake_current_owner_guard(), intake_immutable()');
    }
};
