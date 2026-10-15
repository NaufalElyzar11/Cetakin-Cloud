<?php

namespace Tests\Database\Intake;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\PostgresTestCase;
use Tests\Support\ReviewFixtures;

class ReviewMigrationTest extends PostgresTestCase
{
    use ReviewFixtures;

    protected function tearDown(): void
    {
        $this->removeDesignFixtures();
        File::deleteDirectory($this->privateTestRoot);
        Artisan::call('migrate:fresh', ['--force' => true]);
        parent::tearDown();
    }

    public function test_clean_and_representative_cet007_migrations_retain_requests_versions_intents_and_access(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh', ['--force' => true]));
        $this->assertDatabaseCount('request_review_events', 0);
        $paths = ['database/migrations/2026_10_10_000000_create_customer_access.php', 'database/migrations/2026_10_12_000000_create_staff_access.php',
            'database/migrations/2026_10_12_010000_create_owned_print_intake.php', 'database/migrations/2026_10_14_000000_guard_exact_object_intents.php'];
        $this->assertSame(0, Artisan::call('migrate:fresh', ['--path' => $paths, '--force' => true]));
        $owner = $this->reviewOwner();
        $print = $this->submitted($owner);
        DB::table('sessions')->insert(['id' => 'synthetic-cet007-session', 'user_id' => $owner->id, 'payload' => 'synthetic', 'last_activity' => time()]);
        $tables = ['users', 'customers', 'customer_user', 'sessions', 'access_control', 'staff_responsibility_grants', 'credential_recovery_history',
            'submission_attempts', 'print_requests', 'design_file_versions', 'submission_object_guards', 'submission_object_publications'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->get()->toJson();
        }
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        foreach ($before as $table => $rows) {
            $this->assertSame($rows, DB::table($table)->get()->toJson());
        }
        $this->assertDatabaseHas('request_review_work', ['request_id' => $print->id, 'revision' => 0, 'event_id' => null]);
        $this->assigned($owner, $print);
        $this->actingAs($owner)->get('/staff/requests/'.$print->id)->assertOk();
    }
}
