<?php

namespace Tests\Database\Files;

use App\Access\Actions\RegisterCustomer;
use App\Access\Models\User;
use App\Files\CompatibleDesignObjects;
use App\Files\DesignContent;
use App\Files\DesignObjectStores;
use App\Intake\SubmitPrintRequest;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Support\CompatibleObjects;
use Tests\Support\LostSubmissionResponse;
use Tests\Support\PostgresTestCase;
use Tests\Support\SyntheticDesigns;

class ObjectConcurrencyTest extends PostgresTestCase
{
    use CompatibleObjects, SyntheticDesigns;

    /** @var list<Process> */
    private array $workers = [];

    /** @var list<InputStream> */
    private array $inputs = [];

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->configureObjects();
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
        foreach ($this->inputs as $input) {
            $input->close();
        }
        $this->removeSyntheticObjects();
        $this->removeDesignFixtures();
        File::deleteDirectory($this->privateTestRoot);
        Artisan::call('migrate:fresh', ['--force' => true]);
        parent::tearDown();
    }

    private function fixtureInput(): array
    {
        $actor = app(RegisterCustomer::class)->register('Synthetic race customer', Str::uuid().'@example.test', '000', 'synthetic-password');
        $upload = (new DesignContent)->inspect($this->fixture($this->asciiStl()), 'files.0');

        return ['settings' => $this->objectSettings, 'root' => $this->privateTestRoot, 'actor' => $actor->id,
            'customer' => $actor->customers()->firstOrFail()->id, 'operation' => (string) Str::uuid(),
            'specification' => ['material' => 'PETG', 'color' => 'black', 'quantity' => '1', 'notes' => null], 'uploads' => [$upload]];
    }

    private function worker(array $input, bool $ioBarrier = false): array
    {
        $stream = new InputStream;
        $stream->write(json_encode($input, JSON_THROW_ON_ERROR)."\n");
        $worker = new Process([PHP_BINARY, base_path('tests/Support/object-race-worker.php')], base_path(), timeout: 30);
        $worker->setInput($stream);
        $worker->start();
        $this->workers[] = $worker;
        $this->inputs[] = $stream;
        $deadline = hrtime(true) + 15_000_000_000;
        do {
            if (preg_match('/ready:(\d+)/', $worker->getOutput(), $match)) {
                $pid = (int) $match[1];
                if ($ioBarrier && str_contains($worker->getOutput(), 'barrier')) {
                    return [$worker, $stream, $pid];
                }
                if (! $ioBarrier) {
                    DB::select('select pg_stat_clear_snapshot()');
                    $state = DB::selectOne('select wait_event_type from pg_stat_activity where pid = ?', [$pid]);
                    if ($state?->wait_event_type === 'Lock') {
                        $this->assertNotSame((int) DB::selectOne('select pg_backend_pid() as pid')->pid, $pid);

                        return [$worker, $stream, $pid];
                    }
                }
            }
            if (! $worker->isRunning()) {
                $this->fail('Independent worker exited before its real barrier: '.$worker->getOutput().$worker->getErrorOutput());
            }
        } while (hrtime(true) < $deadline);
        $this->fail('Independent worker did not reach the coordinated barrier.');
    }

    private function workerResult(Process $worker): array
    {
        $worker->wait();
        $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
        $lines = explode("\n", trim($worker->getOutput()));

        return json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
    }

    private function pending(array $input): object
    {
        // Real object publication then a real PostgreSQL rejection establishes
        // committed, successful writer provenance without a confirmed Request.
        DB::unprepared("create function synthetic_race_sql_failure() returns trigger language plpgsql as \$\$ begin raise exception 'synthetic'; end; \$\$; create trigger synthetic_race_sql_failure before insert on print_requests for each row execute function synthetic_race_sql_failure()");
        try {
            app(SubmitPrintRequest::class)->submit(User::findOrFail($input['actor']), $input['customer'], $input['operation'], $input['specification'], $input['uploads']);
            $this->fail('Synthetic SQL confirmation must fail.');
        } catch (\PDOException $exception) {
            $this->assertDatabaseCount('print_requests', 0);
        } finally {
            DB::unprepared('drop trigger synthetic_race_sql_failure on print_requests; drop function synthetic_race_sql_failure()');
        }

        return DB::table('submission_attempts')->where('operation_id', $input['operation'])->first();
    }

    public function test_independent_same_intent_publications_use_real_conditional_objects_and_commit_one_exact_request(): void
    {
        $input = [...$this->fixtureInput(), 'mode' => 'submit', 'publicationBarrier' => true];
        [$a, $ai, $ap] = $this->worker($input, true);
        [$b, $bi, $bp] = $this->worker($input, true);
        $this->assertNotSame($ap, $bp);
        $this->assertDatabaseCount('submission_attempts', 1);
        $this->assertDatabaseCount('submission_object_publications', 2);
        $this->assertDatabaseCount('print_requests', 0);
        $ai->write("release\n");
        $bi->write("release\n");
        $ai->close();
        $bi->close();
        $first = $this->workerResult($a);
        $second = $this->workerResult($b);
        $this->assertTrue($first['ok']);
        $this->assertTrue($second['ok']);
        $this->assertSame($first['result'], $second['result']);
        $this->assertDatabaseCount('print_requests', 1);
        $this->assertDatabaseCount('design_file_versions', 1);
        $this->assertSame(2, DB::table('submission_object_publications')->where('outcome', 'SUCCEEDED')->count());
        $attempt = DB::table('submission_attempts')->first();
        $file = json_decode($attempt->manifest, true)[0];
        $read = app(DesignObjectStores::class)->forAttempt($attempt)->read($attempt->id, $file);
        $this->assertSame($this->asciiStl(), stream_get_contents($read->stream()));
        $read->close();
    }

    public function test_independent_different_content_conditional_puts_to_same_key_leave_only_one_immutable_winner(): void
    {
        $input = $this->fixtureInput();
        $attempt = (string) Str::uuid();
        $id = (string) Str::uuid();
        $one = $this->fixture($this->asciiStl());
        $two = $this->fixture(str_replace('synthetic', 'different', $this->asciiStl()));
        $common = [...$input, 'mode' => 'publish', 'attempt' => $attempt];
        [$a, $ai, $ap] = $this->worker([...$common, 'file' => ['id' => $id, 'size' => $one->getSize(), 'digest' => hash_file('sha256', $one->getPathname())], 'source' => $one->getPathname()], true);
        [$b, $bi, $bp] = $this->worker([...$common, 'file' => ['id' => $id, 'size' => $two->getSize(), 'digest' => hash_file('sha256', $two->getPathname())], 'source' => $two->getPathname()], true);
        $this->assertNotSame($ap, $bp);
        $ai->write("release\n");
        $bi->write("release\n");
        $ai->close();
        $bi->close();
        $first = $this->workerResult($a);
        $second = $this->workerResult($b);
        $statuses = [$first['nativePut'], $second['nativePut']];
        sort($statuses);
        $this->assertSame([200, 412], $statuses);
        $results = [$first['ok'], $second['ok']];
        sort($results);
        $this->assertSame([false, true], $results);
        $store = new CompatibleDesignObjects($this->objectClient, $this->objectSettings['bucket'], $this->objectPrefix);
        $object = $this->objectClient->getObject(['Bucket' => $this->objectSettings['bucket'], 'Key' => $store->key($attempt, $id)]);
        $bytes = (string) $object['Body'];
        $this->assertContains($bytes, [$this->asciiStl(), str_replace('synthetic', 'different', $this->asciiStl())]);
        $this->assertSame(strlen($bytes), (int) $object['ContentLength']);
        $winner = $first['nativePut'] === 200 ? $one : $two;
        $this->assertSame(hash_file('sha256', $winner->getPathname()), hash('sha256', $bytes));
    }

    public function test_confirmation_wins_real_guard_race_and_cleanup_cannot_delete_associated_content(): void
    {
        $input = $this->fixtureInput();
        $attempt = $this->pending($input);
        DB::beginTransaction();
        DB::table('submission_object_guards')->where('attempt_id', $attempt->id)->lockForUpdate()->first();
        [$confirm, , $first] = $this->worker([...$input, 'mode' => 'reconcile']);
        [$claim, , $second] = $this->worker([...$input, 'mode' => 'claim', 'attempt' => $attempt->id]);
        $this->assertNotSame($first, $second);
        DB::commit();
        $this->assertTrue($this->workerResult($confirm)['ok']);
        $this->assertFalse($this->workerResult($claim)['ok']);
        $this->assertDatabaseCount('print_requests', 1);
        $this->assertDatabaseCount('design_file_versions', 1);
        $this->assertDatabaseHas('submission_object_guards', ['attempt_id' => $attempt->id, 'state' => 'OPEN']);
        app(DesignObjectStores::class)->forAttempt($attempt)->read($attempt->id, json_decode($attempt->manifest, true)[0])->close();
    }

    public function test_unused_claim_wins_real_guard_race_and_final_association_is_rejected(): void
    {
        $input = $this->fixtureInput();
        $attempt = $this->pending($input);
        DB::beginTransaction();
        DB::table('submission_object_guards')->where('attempt_id', $attempt->id)->lockForUpdate()->first();
        [$claim, , $first] = $this->worker([...$input, 'mode' => 'claim', 'attempt' => $attempt->id]);
        [$confirm, , $second] = $this->worker([...$input, 'mode' => 'reconcile']);
        $this->assertNotSame($first, $second);
        DB::commit();
        $this->assertTrue($this->workerResult($claim)['ok']);
        $this->assertFalse($this->workerResult($confirm)['ok']);
        $this->assertDatabaseCount('print_requests', 0);
        $this->assertDatabaseCount('design_file_versions', 0);
        $this->assertDatabaseHas('submission_object_guards', ['attempt_id' => $attempt->id, 'state' => 'DELETE_CLAIMED']);
        $this->assertDatabaseHas('submission_attempts', ['id' => $attempt->id, 'request_id' => null]);
    }

    public function test_retry_and_reconciliation_on_independent_connections_resolve_same_result_without_rewriting_files(): void
    {
        $input = $this->fixtureInput();
        $attempt = $this->pending($input);
        DB::beginTransaction();
        DB::table('customer_user')->where('user_id', $input['actor'])->where('customer_id', $input['customer'])->lockForUpdate()->first();
        [$retry, , $first] = $this->worker([...$input, 'mode' => 'submit']);
        [$reconcile, , $second] = $this->worker([...$input, 'mode' => 'reconcile']);
        $this->assertNotSame($first, $second);
        DB::commit();
        $a = $this->workerResult($retry);
        $b = $this->workerResult($reconcile);
        $this->assertTrue($a['ok']);
        $this->assertTrue($b['ok']);
        $this->assertSame($a['result'], $b['result']);
        $this->assertDatabaseCount('print_requests', 1);
        $this->assertDatabaseCount('design_file_versions', 1);
        $this->assertDatabaseCount('submission_attempts', 1);
        app(DesignObjectStores::class)->forAttempt($attempt)->read($attempt->id, json_decode($attempt->manifest, true)[0])->close();
    }

    public function test_lost_customer_response_after_real_sql_commit_is_visible_independently_and_resolves_same_result(): void
    {
        $fixture = $this->fixtureInput();
        $actor = User::findOrFail($fixture['actor']);
        $input = ['operation_id' => $fixture['operation'], 'customer_id' => $fixture['customer'], ...$fixture['specification'], 'files' => [$this->fixture($this->asciiStl())]];
        $this->assertSame(0, DB::transactionLevel());
        // Test-only response fault after a genuine PostgreSQL COMMIT, never a
        // RefreshDatabase savepoint or a production interruption hook.
        $this->app['router']->getRoutes()->getByName('requests.store')->middleware(LostSubmissionResponse::class);
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($actor)->post('/requests', $input);
            $this->fail('Synthetic response interruption must reach the caller.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic lost response after committed SQL result', $exception->getMessage());
        }
        $this->withExceptionHandling();
        $this->assertSame(0, DB::transactionLevel());
        /** @var Connection $independent */
        $independent = DB::build(config('database.connections.pgsql'));
        try {
            $this->assertNotSame((int) DB::selectOne('select pg_backend_pid() as pid')->pid, (int) $independent->selectOne('select pg_backend_pid() as pid')->pid);
            $this->assertSame('cetakin_test', $independent->selectOne('select current_database() as name')->name);
            $attempt = $independent->table('submission_attempts')->first();
            $this->assertNotNull($attempt->request_id);
            $this->assertSame(1, $independent->table('print_requests')->where('id', $attempt->request_id)->count());
            $this->assertSame(1, $independent->table('design_file_versions')->where('request_id', $attempt->request_id)->count());
        } finally {
            $independent->disconnect();
        }
        $this->get('/requests/attempts/'.$fixture['operation'])->assertRedirect('/requests/'.$attempt->request_id);
        $this->post('/requests/attempts/'.$fixture['operation'].'/reconcile')->assertRedirect('/requests/'.$attempt->request_id);
        $this->assertDatabaseCount('print_requests', 1);
        $this->assertDatabaseCount('design_file_versions', 1);
    }
}
