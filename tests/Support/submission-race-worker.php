<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/scripts/dev/PostgresGuard.php';

use App\Access\Models\User;
use App\Files\PrivateDesignFiles;
use App\Intake\SubmitPrintRequest;
use Cetakin\Development\PostgresGuard;
use Illuminate\Contracts\Console\Kernel;

$input = json_decode(fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
PostgresGuard::beforeBootstrap($app);
$app->make(Kernel::class)->bootstrap();
$postgres = PostgresGuard::connection($app, true);
$app['config']->set('filesystems.disks.local.root', $input['root']);
$app->bind(PrivateDesignFiles::class, fn () => new class extends PrivateDesignFiles
{
    private bool $released = false;

    public function publish(string $attempt, array $file, string $source): void
    {
        if (! $this->released) {
            fwrite(STDOUT, 'publishing:'.getmypid()."\n");
            fflush(STDOUT);
            if (trim((string) fgets(STDIN)) !== 'release') {
                throw new RuntimeException('Synthetic race barrier was not released.');
            }
            $this->released = true;
        }
        parent::publish($attempt, $file, $source);
    }
});
fwrite(STDOUT, 'ready:'.$postgres->selectOne('select pg_backend_pid() as pid')->pid."\n");
fflush(STDOUT);
try {
    $id = $app->make(SubmitPrintRequest::class)->submit(User::findOrFail($input['actor']), $input['customer'], $input['operation'], $input['specification'], $input['uploads']);
    fwrite(STDOUT, json_encode(['ok' => true, 'request' => $id], JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    fwrite(STDOUT, json_encode(['ok' => false, 'class' => $exception::class], JSON_THROW_ON_ERROR)."\n");
}
