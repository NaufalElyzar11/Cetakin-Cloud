<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_control', function (Blueprint $table): void {
            $table->unsignedSmallInteger('id')->primary();
            $table->foreignId('initial_owner_user_id')->nullable()->constrained('users');
        });
        DB::statement('alter table access_control add constraint access_control_singleton check (id = 1)');
        DB::table('access_control')->insert(['id' => 1]);
        Schema::create('staff_responsibility_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('responsibility', 16);
            $table->foreignId('granted_by')->nullable()->constrained('users');
            $table->string('authorization_source', 500)->nullable();
            $table->string('grant_reason', 500);
            $table->timestampTz('granted_at');
            $table->foreignId('revoked_by')->nullable()->constrained('users');
            $table->string('revoke_reason', 500)->nullable();
            $table->timestampTz('revoked_at')->nullable();
        });
        DB::statement("alter table staff_responsibility_grants add constraint staff_responsibility_fixed check (responsibility in ('owner', 'operator'))");
        DB::statement("alter table staff_responsibility_grants add constraint staff_grant_provenance check ((granted_by is not null and authorization_source is null) or (granted_by is null and responsibility = 'owner' and authorization_source is not null and length(btrim(authorization_source)) > 0))");
        DB::statement('alter table staff_responsibility_grants add constraint staff_grant_reason check (length(btrim(grant_reason)) > 0)');
        DB::statement('alter table staff_responsibility_grants add constraint staff_revoke_provenance check ((revoked_at is null and revoked_by is null and revoke_reason is null) or (revoked_at is not null and revoked_by is not null and revoke_reason is not null and length(btrim(revoke_reason)) > 0 and revoked_at >= granted_at))');
        DB::statement('create unique index staff_active_responsibility on staff_responsibility_grants (user_id, responsibility) where revoked_at is null');
        Schema::create('credential_recovery_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('acting_user_id')->nullable()->constrained('users');
            $table->string('procedure', 32);
            $table->string('authorization_source', 500);
            $table->string('account_verification', 500);
            $table->string('privileged_verification', 500)->nullable();
            $table->string('infrastructure_authorization', 500)->nullable();
            $table->string('business_authorization', 500)->nullable();
            $table->jsonb('customer_references');
            $table->string('reason', 500);
            $table->string('outcome', 16);
            $table->timestampTz('occurred_at');
        });
        DB::statement("alter table credential_recovery_history add constraint credential_recovery_procedure check (procedure in ('ordinary-account-v1', 'privileged-account-v1', 'sole-owner-offline-v1'))");
        DB::statement("alter table credential_recovery_history add constraint credential_recovery_outcome check (outcome in ('completed', 'denied', 'failed'))");
        DB::statement("alter table credential_recovery_history add constraint credential_recovery_completed_provenance check (outcome <> 'completed' or (length(btrim(authorization_source)) > 0 and length(btrim(account_verification)) > 0 and length(btrim(reason)) > 0 and ((procedure = 'ordinary-account-v1' and acting_user_id is not null) or (procedure = 'privileged-account-v1' and acting_user_id is not null and privileged_verification is not null and length(btrim(privileged_verification)) > 0 and privileged_verification <> account_verification) or (procedure = 'sole-owner-offline-v1' and acting_user_id is null and privileged_verification is not null and length(btrim(privileged_verification)) > 0 and privileged_verification <> account_verification and infrastructure_authorization is not null and length(btrim(infrastructure_authorization)) > 0 and business_authorization is not null and length(btrim(business_authorization)) > 0))))");
        DB::unprepared(<<<'SQL'
            create or replace function protect_access_control() returns trigger language plpgsql as $$
            begin
                if TG_OP = 'DELETE' or (OLD.initial_owner_user_id is not null and NEW is distinct from OLD) then
                    raise exception 'Initial Owner provenance cannot be removed or reused';
                end if;
                return NEW;
            end;
            $$;
            create trigger access_control_retained before update or delete on access_control for each row execute function protect_access_control();
            create or replace function protect_staff_grant() returns trigger language plpgsql as $$
            declare bootstrap_user bigint;
            begin
                select initial_owner_user_id into bootstrap_user from access_control where id = 1 for update;
                if TG_OP = 'DELETE' then
                    raise exception 'Staff grant history is retained';
                end if;
                if TG_OP = 'INSERT' then
                    if NEW.granted_by is null then
                        if bootstrap_user is not null or exists (select 1 from staff_responsibility_grants where responsibility = 'owner') then
                            raise exception 'Initial Owner bootstrap was already used';
                        end if;
                    elsif not exists (select 1 from staff_responsibility_grants where user_id = NEW.granted_by and responsibility = 'owner' and revoked_at is null) then
                        raise exception 'Current Owner authority required';
                    end if;
                    if NEW.revoked_at is not null then raise exception 'A new grant must be active'; end if;
                else
                    if OLD.revoked_at is not null or NEW.revoked_at is null or
                       (to_jsonb(NEW) - array['revoked_by', 'revoke_reason', 'revoked_at']) is distinct from
                       (to_jsonb(OLD) - array['revoked_by', 'revoke_reason', 'revoked_at']) then
                        raise exception 'Grant provenance and prior revocation are immutable';
                    end if;
                    if not exists (select 1 from staff_responsibility_grants where user_id = NEW.revoked_by and responsibility = 'owner' and revoked_at is null) then
                        raise exception 'Current Owner authority required';
                    end if;
                    if OLD.responsibility = 'owner' and (select count(*) from staff_responsibility_grants where responsibility = 'owner' and revoked_at is null) <= 1 then
                        raise exception 'The last active Owner cannot be revoked';
                    end if;
                end if;
                return NEW;
            end;
            $$;
            create trigger staff_grant_retained before insert or update or delete on staff_responsibility_grants for each row execute function protect_staff_grant();
            create or replace function retain_credential_recovery() returns trigger language plpgsql as $$
            begin raise exception 'Recovery history is immutable'; end;
            $$;
            create trigger credential_recovery_retained before update or delete on credential_recovery_history for each row execute function retain_credential_recovery();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_recovery_history');
        Schema::dropIfExists('staff_responsibility_grants');
        Schema::dropIfExists('access_control');
        DB::statement('drop function if exists retain_credential_recovery()');
        DB::statement('drop function if exists protect_staff_grant()');
        DB::statement('drop function if exists protect_access_control()');
    }
};
