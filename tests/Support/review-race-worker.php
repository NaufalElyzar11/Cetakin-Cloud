<?php

use App\Access\Actions\StaffAccess;
use App\Files\DesignObjectStores;
use App\Intake\Models\PrintRequest;
use App\Intake\RequestReview;
use Cetakin\Development\PostgresGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/scripts/dev/PostgresGuard.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
PostgresGuard::beforeBootstrap($app);
$app->make(Kernel::class)->bootstrap();
$connection = PostgresGuard::connection($app, true);
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
config(['filesystems.design_objects.driver' => 'local', 'filesystems.disks.local.root' => $input['root']]);
$app->bind(RequestReview::class, fn () => new class($app->make(DesignObjectStores::class)) extends RequestReview
{
    public function availability(PrintRequest $request): array
    {
        if (DB::transactionLevel() !== 0) {
            throw new RuntimeException('Exact file I/O cannot run in a workflow SQL lock transaction.');
        }
        $result = parent::availability($request);
        echo "io-outside-locks\n";
        flush();

        return $result;
    }
});
echo 'ready:'.$connection->selectOne('select pg_backend_pid() as pid')->pid."\n";
flush();
try {
    if ($input['operation'] === 'revoke') {
        app(StaffAccess::class)->revoke($input['actor'], $input['grant'], 'Synthetic competing revoke');
    } else {
        $reviews = app(RequestReview::class);
        match ($input['operation']) {
            'review' => $reviews->review($input['actor'], $input['request'], $input['payload']),
            'decline' => $reviews->decline($input['actor'], $input['request'], $input['payload']),
            'assign' => $reviews->assign($input['actor'], $input['request'], $input['payload']),
            default => throw new RuntimeException('Unsupported synthetic review operation.'),
        };
    }
    echo json_encode(['ok' => true], JSON_THROW_ON_ERROR)."\n";
} catch (ValidationException|HttpExceptionInterface $exception) {
    echo json_encode(['ok' => false, 'denied' => true], JSON_THROW_ON_ERROR)."\n";
} finally {
    DB::disconnect();
}
