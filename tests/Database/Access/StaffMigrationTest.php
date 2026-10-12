<?php

namespace Tests\Database\Access;

use App\Access\Actions\RegisterCustomer;
use App\Access\Actions\StaffAccess;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\PostgresTestCase;

class StaffMigrationTest extends PostgresTestCase
{
    protected function tearDown(): void
    {
        // Migration probes intentionally commit real foundation fixtures;
        // restore an empty migrated guarded test target for the next class.
        Artisan::call('migrate:fresh', ['--force' => true]);
        parent::tearDown();
    }

    public function test_clean_and_representative_cet004_migrations_preserve_existing_access_data(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh', ['--force' => true]));
        $this->assertSame(1, DB::table('access_control')->count());
        $this->assertSame(0, Artisan::call('migrate:fresh', ['--path' => ['database/migrations/2026_10_10_000000_create_customer_access.php'], '--force' => true]));
        $user = app(RegisterCustomer::class)->register('Existing synthetic customer', 'existing@example.test', '+62 800 000', 'old-password');
        $relationship = (array) DB::table('customer_user')->where('user_id', $user->id)->first();
        DB::table('sessions')->insert(['id' => 'existing-synthetic-session', 'user_id' => $user->id, 'payload' => 'synthetic-existing-payload', 'last_activity' => time()]);
        $before = (array) DB::table('users')->where('id', $user->id)->first();
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertSame($before, (array) DB::table('users')->where('id', $user->id)->first());
        $this->assertSame($relationship, (array) DB::table('customer_user')->where('user_id', $user->id)->first());
        $this->assertDatabaseHas('sessions', ['id' => 'existing-synthetic-session', 'payload' => 'synthetic-existing-payload']);
        $this->assertDatabaseCount('staff_responsibility_grants', 0);
        $this->assertDatabaseCount('credential_recovery_history', 0);
        $this->assertFalse($user->hasResponsibility('owner'));
    }

    public function test_database_rejects_null_or_blank_grant_provenance_and_missing_completed_recovery_authority(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);
        $user = app(RegisterCustomer::class)->register('Synthetic owner', 'owner@example.test', '+62 800 000', 'old-password');
        foreach ([null, ''] as $source) {
            try {
                DB::transaction(fn () => DB::table('staff_responsibility_grants')->insert(['user_id' => $user->id, 'responsibility' => 'owner', 'granted_by' => null, 'authorization_source' => $source, 'grant_reason' => 'Synthetic test', 'granted_at' => now()]));
                $this->fail('Missing source must be rejected by PostgreSQL.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->getCode());
            }
        }
        app(StaffAccess::class)->bootstrap($user->id, 'Synthetic offline source', 'Initial Owner');
        try {
            DB::transaction(fn () => DB::table('credential_recovery_history')->insert(['user_id' => $user->id, 'acting_user_id' => null, 'procedure' => 'sole-owner-offline-v1', 'authorization_source' => 'Synthetic executor', 'account_verification' => 'Synthetic account reference', 'customer_references' => '[]', 'reason' => 'Missing authority test', 'outcome' => 'completed', 'occurred_at' => now()]));
            $this->fail('Incomplete completed recovery provenance must be rejected by PostgreSQL.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }
        $this->assertDatabaseCount('credential_recovery_history', 0);
    }
}
