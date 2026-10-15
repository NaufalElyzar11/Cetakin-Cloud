<?php

namespace Tests\Database\Files;

use App\Access\Actions\RegisterCustomer;
use App\Access\Actions\StaffAccess;
use App\Files\DesignContent;
use App\Files\PrivateDesignFiles;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\PostgresTestCase;
use Tests\Support\SyntheticDesigns;

class ObjectMigrationTest extends PostgresTestCase
{
    use SyntheticDesigns;

    protected function tearDown(): void
    {
        $this->removeDesignFixtures();
        File::deleteDirectory($this->privateTestRoot);
        Artisan::call('migrate:fresh', ['--force' => true]);
        parent::tearDown();
    }

    public function test_clean_and_representative_cet006_migration_preserves_exact_local_confirmed_and_unresolved_history(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh', ['--force' => true]));
        $this->assertDatabaseCount('submission_object_guards', 0);
        $this->assertDatabaseCount('submission_object_publications', 0);
        $this->assertSame(0, Artisan::call('migrate:fresh', ['--path' => ['database/migrations/2026_10_10_000000_create_customer_access.php', 'database/migrations/2026_10_12_000000_create_staff_access.php', 'database/migrations/2026_10_12_010000_create_owned_print_intake.php'], '--force' => true]));
        $actor = app(RegisterCustomer::class)->register('Synthetic prior customer', 'prior@example.test', '000', 'synthetic-password');
        app(StaffAccess::class)->bootstrap($actor->id, 'Synthetic prior trusted source', 'Prior owner');
        $customer = $actor->customers()->firstOrFail()->id;
        DB::table('sessions')->insert(['id' => 'prior-synthetic-session', 'user_id' => $actor->id, 'payload' => 'synthetic', 'last_activity' => time()]);
        $upload = (new DesignContent)->inspect($this->fixture($this->asciiStl()), 'files.0');
        $spec = ['material' => 'PETG', 'color' => 'orange', 'quantity' => '3', 'notes' => null];
        $confirmed = null;
        $attemptIds = [];
        foreach (['confirmed', 'written', 'not-written'] as $kind) {
            $id = (string) Str::uuid();
            $file = ['id' => (string) Str::uuid(), ...array_diff_key($upload, ['path' => true])];
            $attemptIds[] = $id;
            DB::transaction(function () use ($id, $file, $actor, $customer, $spec, $kind, &$confirmed): void {
                DB::table('submission_attempts')->insert(['id' => $id, 'actor_id' => $actor->id, 'customer_id' => $customer, 'operation_id' => (string) Str::uuid(), 'payload_digest' => str_repeat('a', 64), 'specification' => json_encode($spec), 'manifest' => json_encode([$file]), 'created_at' => now()]);
                if ($kind !== 'confirmed') {
                    return;
                }
                $confirmed = (string) Str::uuid();
                DB::table('print_requests')->insert(['id' => $confirmed, 'attempt_id' => $id, 'customer_id' => $customer, 'submitted_by' => $actor->id, ...$spec, 'status' => 'SUBMITTED', 'submitted_at' => now()]);
                DB::table('design_file_versions')->insert(['id' => $file['id'], 'request_id' => $confirmed, 'attempt_id' => $id, 'customer_id' => $customer, 'submitted_by' => $actor->id, 'position' => 1, 'original_name' => $file['name'], 'format' => $file['format'], 'size_bytes' => $file['size'], 'digest' => $file['digest'], 'submitted_at' => now()]);
                DB::table('submission_attempts')->where('id', $id)->update(['request_id' => $confirmed, 'confirmed_at' => now()]);
            });
            if ($kind !== 'not-written') {
                app(PrivateDesignFiles::class)->publish($id, $file, $upload['path']);
            }
        }
        $before = [];
        foreach (['users', 'customers', 'customer_user', 'sessions', 'staff_responsibility_grants', 'credential_recovery_history', 'print_requests', 'design_file_versions'] as $table) {
            $before[$table] = DB::table($table)->get()->toJson();
        }
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        foreach ($before as $table => $rows) {
            $this->assertSame($rows, DB::table($table)->get()->toJson());
        }
        $this->assertDatabaseCount('submission_object_guards', 3);
        $this->assertDatabaseCount('submission_object_publications', 2);
        $this->assertSame(2, DB::table('submission_object_publications')->where('outcome', 'UNKNOWN')->count());
        foreach ($attemptIds as $id) {
            $this->assertDatabaseHas('submission_attempts', ['id' => $id, 'object_driver' => 'local', 'object_namespace' => 'local', 'object_endpoint' => str_repeat('0', 64)]);
            $this->assertDatabaseHas('submission_object_guards', ['attempt_id' => $id, 'state' => 'OPEN']);
        }
        $this->actingAs($actor)->get('/requests/'.$confirmed)->assertOk();
        $version = DB::table('design_file_versions')->first();
        $download = $this->get('/requests/'.$confirmed.'/files/'.$version->id)->assertOk();
        $this->assertSame($this->asciiStl(), $download->streamedContent());
    }
}
