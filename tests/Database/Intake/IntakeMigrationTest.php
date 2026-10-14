<?php

namespace Tests\Database\Intake;

use App\Access\Actions\RegisterCustomer;
use App\Access\Actions\StaffAccess;
use App\Files\DesignContent;
use App\Intake\SubmitPrintRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDOException;
use Tests\Support\PostgresTestCase;
use Tests\Support\SyntheticDesigns;

class IntakeMigrationTest extends PostgresTestCase
{
    use SyntheticDesigns;

    protected function tearDown(): void
    {
        $this->removeDesignFixtures();
        File::deleteDirectory($this->privateTestRoot);
        Artisan::call('migrate:fresh', ['--force' => true]);
        parent::tearDown();
    }

    public function test_clean_and_representative_cet005_migrations_preserve_users_relationships_roles_sessions_and_history(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh', ['--force' => true]));
        $this->assertDatabaseCount('print_requests', 0);
        $this->assertSame(0, Artisan::call('migrate:fresh', ['--path' => ['database/migrations/2026_10_10_000000_create_customer_access.php', 'database/migrations/2026_10_12_000000_create_staff_access.php'], '--force' => true]));
        $actor = app(RegisterCustomer::class)->register('Synthetic existing owner', 'existing@example.test', '000', 'synthetic-password');
        app(StaffAccess::class)->bootstrap($actor->id, 'Synthetic offline source', 'Existing Owner');
        DB::table('sessions')->insert(['id' => 'synthetic-existing-session', 'user_id' => $actor->id, 'payload' => 'synthetic', 'last_activity' => time()]);
        $before = [];
        foreach (['users', 'customers', 'customer_user', 'sessions', 'staff_responsibility_grants', 'access_control'] as $table) {
            $before[$table] = DB::table($table)->get()->toJson();
        }
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        foreach ($before as $table => $rows) {
            $this->assertSame($rows, DB::table($table)->get()->toJson());
        }
        $this->assertDatabaseCount('submission_attempts', 0);
        $this->assertDatabaseCount('design_file_versions', 0);
    }

    private function violation(callable $operation): void
    {
        try {
            DB::transaction($operation);
            $this->fail('PostgreSQL must reject broken intake provenance or immutable history.');
        } catch (PDOException $exception) {
            $this->assertContains((string) $exception->getCode(), ['23514', '23503', '23505']);
        }
    }

    public function test_postgresql_enforces_exact_associations_complete_selection_ownership_and_immutability(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);
        $actor = app(RegisterCustomer::class)->register('Synthetic customer', 'customer@example.test', '000', 'synthetic-password');
        $other = app(RegisterCustomer::class)->register('Synthetic other', 'other@example.test', '000', 'synthetic-password');
        $upload = (new DesignContent)->inspect($this->fixture($this->asciiStl()), 'files.0');
        $spec = ['material' => 'PETG', 'color' => 'white', 'quantity' => '1', 'notes' => null];
        $request = app(SubmitPrintRequest::class)->submit($actor, $actor->customers()->firstOrFail()->id, (string) Str::uuid(), $spec, [$upload]);
        $attempt = DB::table('submission_attempts')->first();
        $version = (array) DB::table('design_file_versions')->first();
        $this->violation(fn () => DB::table('print_requests')->where('id', $request)->update(['quantity' => 2]));
        $this->violation(fn () => DB::table('design_file_versions')->where('id', $version['id'])->delete());
        $this->violation(fn () => DB::table('submission_attempts')->where('id', $attempt->id)->update(['payload_digest' => str_repeat('0', 64)]));
        $this->violation(fn () => DB::table('design_file_versions')->insert([...$version, 'id' => (string) Str::uuid(), 'position' => 2]));
        $this->violation(fn () => DB::table('submission_attempts')->insert([
            'id' => (string) Str::uuid(), 'actor_id' => $actor->id, 'customer_id' => $other->customers()->firstOrFail()->id,
            'operation_id' => (string) Str::uuid(), 'payload_digest' => str_repeat('0', 64), 'specification' => json_encode($spec),
            'manifest' => $attempt->manifest, 'created_at' => now(),
        ]));
        $pending = (string) Str::uuid();
        $manifest = [['id' => (string) Str::uuid(), 'name' => 'missing.stl', 'format' => 'STL', 'size' => 1, 'digest' => str_repeat('0', 64)]];
        DB::table('submission_attempts')->insert(['id' => $pending, 'actor_id' => $actor->id, 'customer_id' => $attempt->customer_id, 'operation_id' => (string) Str::uuid(), 'payload_digest' => str_repeat('0', 64), 'specification' => json_encode($spec), 'manifest' => json_encode($manifest), 'created_at' => now()]);
        $this->violation(fn () => DB::table('submission_attempts')->where('id', $pending)->update(['request_id' => $request, 'confirmed_at' => now()]));
        $this->violation(function () use ($pending, $actor, $attempt, $spec): void {
            $id = (string) Str::uuid();
            DB::table('print_requests')->insert(['id' => $id, 'attempt_id' => $pending, 'customer_id' => $attempt->customer_id, 'submitted_by' => $actor->id, ...$spec, 'status' => 'SUBMITTED', 'submitted_at' => now()]);
            DB::table('submission_attempts')->where('id', $pending)->update(['request_id' => $id, 'confirmed_at' => now()]);
            // Commit forces the deferred complete-file guard: no versions exists.
        });
        $this->assertDatabaseCount('print_requests', 1);
        $this->assertDatabaseCount('design_file_versions', 1);
        $this->assertDatabaseHas('submission_attempts', ['id' => $pending, 'request_id' => null]);
    }
}
