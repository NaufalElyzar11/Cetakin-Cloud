<?php

namespace Tests\Database\Intake;

use App\Access\Actions\RegisterCustomer;
use App\Files\DesignContent;
use App\Files\PrivateDesignFiles;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Support\PostgresTestCase;
use Tests\Support\SyntheticDesigns;

class SubmissionConcurrencyTest extends PostgresTestCase
{
    use SyntheticDesigns;

    /** @var list<Process> */
    private array $workers = [];

    /** @var list<InputStream> */
    private array $inputs = [];

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        foreach ($this->inputs as $input) {
            $input->close();
        }
        $this->removeDesignFixtures();
        File::deleteDirectory($this->privateTestRoot);
        Artisan::call('migrate:fresh', ['--force' => true]);
        parent::tearDown();
    }

    private function worker(array $input): array
    {
        $stream = new InputStream;
        $stream->write(json_encode($input, JSON_THROW_ON_ERROR)."\n");
        $process = new Process([PHP_BINARY, base_path('tests/Support/submission-race-worker.php')], base_path(), ['DESIGN_OBJECT_DRIVER' => 's3', 'CETAKIN_S3_BUCKET' => 'synthetic-development-bucket-never-use'], timeout: 25);
        $process->setInput($stream);
        $process->start();
        $this->workers[] = $process;
        $this->inputs[] = $stream;
        $this->barrier($process, 'publishing:');

        return [$process, $stream];
    }

    private function barrier(Process $worker, string $message): void
    {
        $deadline = hrtime(true) + 10_000_000_000;
        do {
            if (str_contains($worker->getOutput(), $message)) {
                return;
            }
            if (! $worker->isRunning()) {
                $this->fail('Worker exited before its real I/O barrier: '.$worker->getOutput().$worker->getErrorOutput());
            }
        } while (hrtime(true) < $deadline);
        $this->fail('Worker did not reach its real I/O barrier.');
    }

    public function test_independent_connections_competing_same_attempt_publish_one_exact_selection_and_one_request(): void
    {
        $actor = app(RegisterCustomer::class)->register('Synthetic race customer', 'race@example.test', '000', 'synthetic-password');
        $upload = (new DesignContent)->inspect($this->fixture($this->asciiStl(), 'race.stl', 2_000_000), 'files.0');
        $input = ['root' => $this->privateTestRoot, 'actor' => $actor->id, 'customer' => $actor->customers()->firstOrFail()->id,
            'operation' => (string) Str::uuid(), 'specification' => ['material' => 'PETG', 'color' => 'black', 'quantity' => '2', 'notes' => null], 'uploads' => [$upload]];
        [$first, $firstInput] = $this->worker($input);
        [$second, $secondInput] = $this->worker($input);
        preg_match('/ready:(\d+)/', $first->getOutput(), $a);
        preg_match('/ready:(\d+)/', $second->getOutput(), $b);
        $this->assertNotSame($a[1], $b[1]);
        $this->assertNotSame((string) DB::selectOne('select pg_backend_pid() as pid')->pid, $a[1]);
        // Both have committed the same intent and are blocked outside SQL at
        // real file I/O, not sleeping or pretending a sequential retry races.
        $this->assertDatabaseCount('submission_attempts', 1);
        $this->assertDatabaseCount('print_requests', 0);
        $firstInput->write("release\n");
        $secondInput->write("release\n");
        $firstInput->close();
        $secondInput->close();
        $results = [];
        foreach ([$first, $second] as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            $lines = explode("\n", trim($worker->getOutput()));
            $result = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
            $this->assertTrue($result['ok'], $worker->getOutput());
            $results[] = $result['request'];
        }
        $this->assertSame($results[0], $results[1]);
        $this->assertDatabaseCount('print_requests', 1);
        $this->assertDatabaseCount('design_file_versions', 1);
        $version = DB::table('design_file_versions')->first();
        $path = app(PrivateDesignFiles::class)->path($version->attempt_id, $version->id);
        $this->assertSame($upload['digest'], hash_file('sha256', $path));
        $this->assertSame(file_get_contents($upload['path']), file_get_contents($path));
    }
}
