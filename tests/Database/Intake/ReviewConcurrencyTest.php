<?php

namespace Tests\Database\Intake;

use App\Access\Actions\StaffAccess;
use App\Intake\RequestReview;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\Support\PostgresTestCase;
use Tests\Support\ReviewFixtures;

class ReviewConcurrencyTest extends PostgresTestCase
{
    use ReviewFixtures;

    /** @var list<Process> */
    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();
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
        $this->removeDesignFixtures();
        File::deleteDirectory($this->privateTestRoot);
        Artisan::call('migrate:fresh', ['--force' => true]);
        parent::tearDown();
    }

    private function worker(array $input): Process
    {
        $worker = new Process([PHP_BINARY, base_path('tests/Support/review-race-worker.php')], base_path(), timeout: 20);
        $worker->setInput(json_encode(['root' => $this->privateTestRoot, ...$input], JSON_THROW_ON_ERROR));
        $worker->start();
        $this->workers[] = $worker;
        $deadline = hrtime(true) + 12_000_000_000;
        do {
            if (preg_match('/ready:(\d+)/', $worker->getOutput(), $match)) {
                DB::select('select pg_stat_clear_snapshot()');
                $pid = (int) $match[1];
                $activity = DB::selectOne('select wait_event_type from pg_stat_activity where pid = ?', [$pid]);
                if ($activity?->wait_event_type === 'Lock') {
                    $this->assertNotSame((int) DB::selectOne('select pg_backend_pid() as pid')->pid, $pid);
                    if ($input['operation'] === 'review') {
                        $this->assertStringContainsString('io-outside-locks', $worker->getOutput());
                    }

                    return $worker;
                }
            }
            if (! $worker->isRunning()) {
                $this->fail('Review worker exited before the real PostgreSQL lock barrier: '.$worker->getOutput().$worker->getErrorOutput());
            }
        } while (hrtime(true) < $deadline);
        $this->fail('Review worker failed to reach its observed PostgreSQL barrier.');
    }

    private function raceResult(Process $worker): bool
    {
        $worker->wait();
        $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
        $lines = explode("\n", trim($worker->getOutput()));

        return json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR)['ok'];
    }

    private function barrier(): void
    {
        DB::beginTransaction();
        DB::table('access_control')->where('id', 1)->lockForUpdate()->first();
    }

    public function test_independent_review_and_decline_contenders_commit_only_one_current_revision(): void
    {
        $owner = $this->reviewOwner();
        $print = $this->submitted($this->reviewUser('customer@example.test'));
        $this->assigned($owner, $print);
        $this->barrier();
        $a = $this->worker(['operation' => 'review', 'actor' => $owner->id, 'request' => $print->id, 'payload' => $this->reviewInput($owner, $print)]);
        $b = $this->worker(['operation' => 'decline', 'actor' => $owner->id, 'request' => $print->id, 'payload' => ['revision' => 1, 'reason' => 'Synthetic capability conflict', 'customer_reason' => 'Cannot provide this work']]);
        DB::commit();
        $results = [$this->raceResult($a), $this->raceResult($b)];
        sort($results);
        $this->assertSame([false, true], $results);
        $this->assertDatabaseCount('request_review_events', 2);
        $event = DB::table('request_review_events')->where('revision', 2)->first();
        $this->assertSame($event->kind === 'DECLINE' ? 'DECLINED' : 'SUBMITTED', $print->fresh()->status);
        $this->assertSame(2, app(RequestReview::class)->currentWork($print->id)->revision);
        $this->assertDatabaseCount('design_file_versions', 1);
    }

    public function test_reassignment_committing_before_waiting_review_rejects_the_old_assignees_decision(): void
    {
        $owner = $this->reviewOwner();
        $other = $this->reviewUser('other@example.test');
        app(StaffAccess::class)->grant($owner->id, $other->id, 'owner', 'Second Owner');
        $print = $this->submitted($this->reviewUser('customer@example.test'));
        $this->assigned($owner, $print);
        $this->barrier();
        $worker = $this->worker(['operation' => 'review', 'actor' => $owner->id, 'request' => $print->id, 'payload' => $this->reviewInput($owner, $print)]);
        app(RequestReview::class)->assign($other->id, $print->id, ['revision' => 1, 'responsible_user_id' => $other->id, 'next_action' => 'New responsible reviewer', 'work_disposition' => 'ACTIONABLE', 'reason' => 'Explicit reassignment wins']);
        DB::commit();
        $this->assertFalse($this->raceResult($worker));
        $this->assertSame($other->currentResponsibilityGrantId('owner'), app(RequestReview::class)->currentWork($print->id)->responsible_grant_id);
        $this->assertSame(0, DB::table('request_review_events')->where('kind', 'REVIEW')->count());
        $this->assertDatabaseCount('request_review_events', 2);
    }

    public function test_owner_revocation_committing_before_waiting_review_removes_current_authority(): void
    {
        $owner = $this->reviewOwner();
        $other = $this->reviewUser('other@example.test');
        app(StaffAccess::class)->grant($owner->id, $other->id, 'owner', 'Second Owner');
        $print = $this->submitted($this->reviewUser('customer@example.test'));
        $this->assigned($owner, $print);
        $this->barrier();
        $worker = $this->worker(['operation' => 'review', 'actor' => $owner->id, 'request' => $print->id, 'payload' => $this->reviewInput($owner, $print)]);
        app(StaffAccess::class)->revoke($other->id, $owner->currentResponsibilityGrantId('owner'), 'Revoke commits first');
        DB::commit();
        $this->assertFalse($this->raceResult($worker));
        $this->assertFalse(app(RequestReview::class)->canReview($owner->id, $print));
        $this->assertDatabaseCount('request_review_events', 1);
        $this->assertSame('SUBMITTED', $print->fresh()->status);
    }

    public function test_assignment_target_revoked_before_waiting_assignment_cannot_receive_stale_scope(): void
    {
        $owner = $this->reviewOwner();
        $other = $this->reviewUser('other@example.test');
        $grant = app(StaffAccess::class)->grant($owner->id, $other->id, 'owner', 'Second Owner');
        $print = $this->submitted($this->reviewUser('customer@example.test'));
        $this->barrier();
        $worker = $this->worker(['operation' => 'assign', 'actor' => $owner->id, 'request' => $print->id, 'payload' => ['revision' => 0, 'responsible_user_id' => $other->id, 'next_action' => 'Assign target', 'work_disposition' => 'ACTIONABLE', 'reason' => 'Stale target test']]);
        app(StaffAccess::class)->revoke($owner->id, $grant, 'Target revoked first');
        DB::commit();
        $this->assertFalse($this->raceResult($worker));
        $this->assertDatabaseCount('request_review_events', 0);
        $this->assertNull(app(RequestReview::class)->currentWork($print->id)->responsible_grant_id);
    }

    public function test_assignment_before_competing_revocation_keeps_history_but_missing_current_coverage(): void
    {
        $owner = $this->reviewOwner();
        $other = $this->reviewUser('other@example.test');
        $grant = app(StaffAccess::class)->grant($owner->id, $other->id, 'owner', 'Second Owner');
        $print = $this->submitted($this->reviewUser('customer@example.test'));
        $this->barrier();
        $worker = $this->worker(['operation' => 'revoke', 'actor' => $owner->id, 'grant' => $grant]);
        app(RequestReview::class)->assign($owner->id, $print->id, ['revision' => 0, 'responsible_user_id' => $other->id, 'next_action' => 'Pending review', 'work_disposition' => 'WAITING', 'reason' => 'Assign commits before revocation']);
        DB::commit();
        $this->assertTrue($this->raceResult($worker));
        $this->assertDatabaseHas('request_review_events', ['kind' => 'ASSIGN', 'responsible_grant_id' => $grant]);
        $this->assertFalse(app(RequestReview::class)->canReview($other->id, $print));
        $this->assertSame('Pending review', app(RequestReview::class)->currentWork($print->id)->next_action);
        $this->assertSame('SUBMITTED', $print->fresh()->status);
    }
}
