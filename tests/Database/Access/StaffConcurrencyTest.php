<?php

namespace Tests\Database\Access;

use App\Access\Actions\RegisterCustomer;
use App\Access\Actions\StaffAccess;
use App\Access\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;
use Tests\Support\PostgresTestCase;

class StaffConcurrencyTest extends PostgresTestCase
{
    /** @var list<Process> */
    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Guarded dedicated test database, no RefreshDatabase outer transaction:
        // workers must observe committed fixtures and true PostgreSQL row locks.
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($this->workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        // Committed race fixtures cannot leak into later RefreshDatabase tests.
        Artisan::call('migrate:fresh', ['--force' => true]);
        parent::tearDown();
    }

    private function user(string $email): User
    {
        return app(RegisterCustomer::class)->register('Synthetic race actor', $email, '+62 800 000', 'old-password');
    }

    private function worker(array $input): Process
    {
        $process = new Process([PHP_BINARY, base_path('tests/Support/staff-race-worker.php')], base_path(), timeout: 15);
        $process->setInput(json_encode($input, JSON_THROW_ON_ERROR));
        $process->start();
        $this->workers[] = $process;
        $deadline = microtime(true) + 10;
        $pid = null;
        do {
            if (preg_match('/ready:(\d+)/', $process->getOutput(), $matches)) {
                $pid = (int) $matches[1];
                DB::select('select pg_stat_clear_snapshot()');
                $activity = DB::selectOne('select wait_event_type from pg_stat_activity where pid = ?', [$pid]);
                if ($activity !== null && $activity->wait_event_type === 'Lock') {
                    $this->assertNotSame((int) DB::selectOne('select pg_backend_pid() as pid')->pid, $pid);

                    return $process;
                }
            }
            if (! $process->isRunning()) {
                $this->fail('Race worker exited before reaching the real lock: '.$process->getOutput().$process->getErrorOutput());
            }
        } while (microtime(true) < $deadline);
        $process->stop();
        $this->fail('Worker did not reach the PostgreSQL lock barrier.');
    }

    private function workerResult(Process $process): bool
    {
        $process->wait();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $lines = explode("\n", trim($process->getOutput()));

        return json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR)['ok'];
    }

    public function test_two_competing_bootstraps_commit_only_one_initial_owner(): void
    {
        $first = $this->user('first@example.test');
        $second = $this->user('second@example.test');
        DB::beginTransaction();
        DB::table('access_control')->where('id', 1)->lockForUpdate()->first();
        $a = $this->worker(['operation' => 'bootstrap', 'target' => $first->id]);
        $b = $this->worker(['operation' => 'bootstrap', 'target' => $second->id]);
        DB::commit();
        $results = [$this->workerResult($a), $this->workerResult($b)];
        sort($results);
        $this->assertSame([false, true], $results);
        $this->assertSame(1, DB::table('staff_responsibility_grants')->where('responsibility', 'owner')->whereNull('revoked_at')->count());
        $this->assertNotNull(DB::table('access_control')->value('initial_owner_user_id'));
    }

    public function test_mutual_owner_revocations_cannot_remove_both_owners(): void
    {
        $first = $this->user('first@example.test');
        $second = $this->user('second@example.test');
        $firstGrant = app(StaffAccess::class)->bootstrap($first->id, 'Synthetic offline source', 'First Owner');
        $secondGrant = app(StaffAccess::class)->grant($first->id, $second->id, 'owner', 'Second Owner');
        DB::beginTransaction();
        DB::table('access_control')->where('id', 1)->lockForUpdate()->first();
        $a = $this->worker(['operation' => 'revoke', 'actor' => $first->id, 'grant' => $secondGrant]);
        $b = $this->worker(['operation' => 'revoke', 'actor' => $second->id, 'grant' => $firstGrant]);
        DB::commit();
        $results = [$this->workerResult($a), $this->workerResult($b)];
        sort($results);
        $this->assertSame([false, true], $results);
        $this->assertSame(1, DB::table('staff_responsibility_grants')->where('responsibility', 'owner')->whereNull('revoked_at')->count());
        $this->assertSame(1, DB::table('staff_responsibility_grants')->whereNotNull('revoked_at')->count());
    }

    public function test_staff_grant_committing_before_waiting_ordinary_recovery_denies_that_recovery(): void
    {
        $owner = $this->user('owner@example.test');
        $target = $this->user('target@example.test');
        app(StaffAccess::class)->bootstrap($owner->id, 'Synthetic offline source', 'First Owner');
        DB::beginTransaction();
        DB::table('access_control')->where('id', 1)->lockForUpdate()->first();
        $worker = $this->worker(['operation' => 'recover', 'actor' => $owner->id, 'target' => $target->id]);
        app(StaffAccess::class)->grant($owner->id, $target->id, 'operator', 'Staff grant commits first');
        DB::commit();
        $this->assertFalse($this->workerResult($worker));
        $this->assertTrue(Hash::check('old-password', $target->fresh()->password));
        $this->assertTrue($target->hasResponsibility('operator'));
        $this->assertDatabaseHas('credential_recovery_history', ['user_id' => $target->id, 'outcome' => 'denied']);
    }

    public function test_acting_owner_revocation_committing_before_waiting_recovery_denies_reset(): void
    {
        $owner = $this->user('owner@example.test');
        $other = $this->user('other@example.test');
        $target = $this->user('target@example.test');
        $ownerGrant = app(StaffAccess::class)->bootstrap($owner->id, 'Synthetic offline source', 'First Owner');
        app(StaffAccess::class)->grant($owner->id, $other->id, 'owner', 'Second Owner');
        DB::beginTransaction();
        DB::table('access_control')->where('id', 1)->lockForUpdate()->first();
        $worker = $this->worker(['operation' => 'recover', 'actor' => $owner->id, 'target' => $target->id]);
        app(StaffAccess::class)->revoke($other->id, $ownerGrant, 'Acting authority removed first');
        DB::commit();
        $this->assertFalse($this->workerResult($worker));
        $this->assertTrue(Hash::check('old-password', $target->fresh()->password));
        $this->assertDatabaseHas('credential_recovery_history', ['user_id' => $target->id, 'acting_user_id' => null, 'outcome' => 'denied']);
    }

    public function test_eligible_recovery_committing_first_allows_a_separate_later_staff_grant(): void
    {
        $owner = $this->user('owner@example.test');
        $target = $this->user('target@example.test');
        app(StaffAccess::class)->bootstrap($owner->id, 'Synthetic offline source', 'First Owner');
        DB::beginTransaction();
        DB::table('access_control')->where('id', 1)->lockForUpdate()->first();
        $worker = $this->worker(['operation' => 'grant', 'actor' => $owner->id, 'target' => $target->id]);
        app(StaffAccess::class)->recover($owner->id, 'old-password', $target->id, 'new-password', ['procedure' => 'ordinary-account-v1', 'source' => 'Synthetic authorized executor', 'account' => 'Synthetic whole-account verification', 'reason' => 'Recovery commits first']);
        DB::commit();
        $this->assertTrue($this->workerResult($worker));
        $this->assertTrue(Hash::check('new-password', $target->fresh()->password));
        $this->assertTrue($target->hasResponsibility('operator'));
        $this->assertDatabaseHas('credential_recovery_history', ['user_id' => $target->id, 'outcome' => 'completed']);
    }
}
