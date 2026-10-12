<?php

// Dedicated PostgreSQL child process. Operation input travels through stdin.
// Passwords below are synthetic test values, never real credentials or argv.

use App\Access\Actions\StaffAccess;
use Cetakin\Development\PostgresGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/scripts/dev/PostgresGuard.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
PostgresGuard::beforeBootstrap($app);
$app->make(Kernel::class)->bootstrap();
$connection = PostgresGuard::connection($app, true);
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
echo 'ready:'.$connection->selectOne('select pg_backend_pid() as pid')->pid."\n";
flush();
try {
    $access = $app->make(StaffAccess::class);
    switch ($input['operation']) {
        case 'bootstrap':
            $access->bootstrap($input['target'], 'Synthetic race authorization', 'Competing bootstrap test');
            break;
        case 'revoke':
            $access->revoke($input['actor'], $input['grant'], 'Concurrent revocation test');
            break;
        case 'grant':
            $access->grant($input['actor'], $input['target'], 'operator', 'Separate staff grant after recovery');
            break;
        case 'recover':
            $access->recover($input['actor'], 'old-password', $input['target'], 'new-password', ['procedure' => 'ordinary-account-v1', 'source' => 'Synthetic race executor', 'account' => 'Synthetic whole-account verification', 'reason' => 'Recovery race test']);
            break;
        default:
            throw new RuntimeException('Unsupported test operation.');
    }
    echo json_encode(['ok' => true], JSON_THROW_ON_ERROR)."\n";
} catch (ValidationException) {
    echo json_encode(['ok' => false, 'denied' => true], JSON_THROW_ON_ERROR)."\n";
} finally {
    DB::disconnect();
}
