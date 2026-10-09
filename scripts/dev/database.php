<?php

// Infrastructure smoke/reset only. No business migrations or feature setup.
use Cetakin\Development\PostgresGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/PostgresGuard.php';

$mode = $argv[1] ?? '';
$test = in_array($mode, ['test-check', 'reset-test'], true);
if (! in_array($mode, ['dev-check', 'migrate-dev', 'test-check', 'reset-test'], true)) {
    fwrite(STDERR, "Unknown database check action.\n");
    exit(1);
}

try {
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    PostgresGuard::beforeBootstrap($app);
    $app->make(Kernel::class)->bootstrap();
    $connection = PostgresGuard::connection($app, $test);

    if ($mode === 'reset-test') {
        if (getenv('CETAKIN_TEST_RESET') !== 'confirmed') {
            throw new RuntimeException('Test reset was not explicitly confirmed.');
        }
        $result = Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
        if ($result !== 0) {
            throw new RuntimeException('Migration reset failed.');
        }
        echo Artisan::output();
    }
    if ($mode === 'migrate-dev') {
        $result = Artisan::call('migrate', ['--no-interaction' => true]);
        if ($result !== 0) {
            throw new RuntimeException('Migration failed.');
        }
        echo Artisan::output();
    }

    echo 'Verified isolated '.($test ? 'cetakin_test' : 'cetakin_dev').' connection; PostgreSQL '.$connection->selectOne('show server_version')->server_version.".\n";
} catch (Throwable $exception) {
    // Do not print credentials, connection strings or configuration in failure output.
    fwrite(STDERR, "Database smoke/reset failed; confirm local services and the documented target.\n");
    exit(1);
}
