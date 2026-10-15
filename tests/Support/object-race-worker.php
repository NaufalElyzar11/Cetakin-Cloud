<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/scripts/dev/PostgresGuard.php';

use App\Access\Models\User;
use App\Files\CompatibleDesignObjects;
use App\Files\DesignObjectStores;
use App\Files\ExactDesignObjects;
use App\Files\SyntheticDisposition;
use App\Files\UnusedDesignObjects;
use App\Files\VerifiedDesignRead;
use App\Intake\SubmitPrintRequest;
use Aws\Exception\AwsException;
use Cetakin\Development\PostgresGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$input = json_decode(fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
PostgresGuard::beforeBootstrap($app);
$app->make(Kernel::class)->bootstrap();
$postgres = PostgresGuard::connection($app, true);
$app['config']->set('filesystems.design_objects', ['driver' => 's3', 's3' => $input['settings']]);
$app['config']->set('filesystems.disks.local.root', $input['root']);
$barrier = static function (): void {
    fwrite(STDOUT, "barrier\n");
    fflush(STDOUT);
    if (trim((string) fgets(STDIN)) !== 'release') {
        throw new RuntimeException('Synthetic object race was not released.');
    }
};
if ($input['mode'] === 'submit' && ($input['publicationBarrier'] ?? false)) {
    $app->bind(DesignObjectStores::class, fn () => new class($barrier) extends DesignObjectStores
    {
        public function __construct(private Closure $barrier) {}

        public function forAttempt(object $attempt): ExactDesignObjects
        {
            return new class(parent::forAttempt($attempt), $this->barrier) implements ExactDesignObjects
            {
                public function __construct(private ExactDesignObjects $real, private Closure $barrier) {}

                public function publish(string $attempt, array $file, string $source): void
                {
                    ($this->barrier)();
                    $this->real->publish($attempt, $file, $source);
                }

                public function read(string $attempt, array $file): VerifiedDesignRead
                {
                    return $this->real->read($attempt, $file);
                }

                public function remove(string $attempt, string $file): void
                {
                    $this->real->remove($attempt, $file);
                }

                public function absent(string $attempt, string $file): bool
                {
                    return $this->real->absent($attempt, $file);
                }
            };
        }
    });
}
fwrite(STDOUT, 'ready:'.$postgres->selectOne('select pg_backend_pid() as pid')->pid."\n");
fflush(STDOUT);
try {
    $result = null;
    $nativePut = null;
    if ($input['mode'] === 'publish') {
        $client = app(DesignObjectStores::class)->client();
        $store = new CompatibleDesignObjects($client, $input['settings']['bucket'], $input['settings']['prefix']);
        $barrier();
        $body = fopen($input['source'], 'rb');
        try {
            $created = $client->putObject(['Bucket' => $input['settings']['bucket'], 'Key' => $store->key($input['attempt'], $input['file']['id']), 'IfNoneMatch' => '*', 'Body' => $body, 'ContentLength' => $input['file']['size']]);
            $nativePut = (int) $created['@metadata']['statusCode'];
        } catch (AwsException $exception) {
            $nativePut = $exception->getStatusCode();
            throw $exception;
        } finally {
            fclose($body);
        }
    } elseif ($input['mode'] === 'submit') {
        $result = app(SubmitPrintRequest::class)->submit(User::findOrFail($input['actor']), $input['customer'], $input['operation'], $input['specification'], $input['uploads']);
    } elseif ($input['mode'] === 'reconcile') {
        $result = app(SubmitPrintRequest::class)->reconcile(User::findOrFail($input['actor']), $input['operation']);
    } elseif ($input['mode'] === 'claim') {
        $attempt = DB::table('submission_attempts')->where('id', $input['attempt'])->first();
        $permit = SyntheticDisposition::authorize($attempt, 'Synthetic concurrent fixture owner', 'Explicit disposable fixture');
        app(UnusedDesignObjects::class)->classify($permit);
        $result = app(UnusedDesignObjects::class)->claim($permit);
    }
    fwrite(STDOUT, json_encode(['ok' => true, 'result' => $result, 'nativePut' => $nativePut], JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    fwrite(STDOUT, json_encode(['ok' => false, 'class' => $exception::class, 'nativePut' => $nativePut ?? null], JSON_THROW_ON_ERROR)."\n");
}
