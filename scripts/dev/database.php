<?php

// Infrastructure smoke/reset only. CET-003 owns the feature-test harness.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$mode = $argv[1] ?? '';
$test = in_array($mode, ['test-check', 'reset-test'], true);
if (! in_array($mode, ['dev-check', 'migrate-dev', 'test-check', 'reset-test'], true)) {
    fwrite(STDERR, "Unknown database check action.\n");
    exit(1);
}

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
if ($app->configurationIsCached()) {
    fwrite(STDERR, "Refusing cached configuration; run the documented config-clear command.\n");
    exit(1);
}

$app->make(Kernel::class)->bootstrap();
if ($app->bound('config_loaded_from_cache') && $app->make('config_loaded_from_cache')) {
    fwrite(STDERR, "Refusing cached configuration loaded during environment initialization.\n");
    exit(1);
}
$expected = $test ? 'cetakin_test' : 'cetakin_dev';
$connection = config('database.connections.pgsql');
if (getenv('CETAKIN_DEV_CONTAINER') !== '1'
    || ! preg_match('/^cetakin-[a-f0-9]{12}$/', getenv('CETAKIN_DEV_PROJECT') ?: '')
    || ! $app->environment($test ? 'testing' : 'local')
    || config('database.default') !== 'pgsql'
    || $connection['driver'] !== 'pgsql'
    || $connection['host'] !== 'postgres'
    || (string) $connection['port'] !== '5432'
    || $connection['database'] !== $expected
    || $connection['username'] !== $expected
) {
    fwrite(STDERR, "Refusing database action: not the expected isolated local target.\n");
    exit(1);
}

foreach (['url', 'read', 'write', 'pool', 'pooled', 'direct'] as $alternateTarget) {
    if (! empty($connection[$alternateTarget])) {
        fwrite(STDERR, "Refusing alternative database targeting configuration.\n");
        exit(1);
    }
}
if (getenv('DB_URL') || getenv('DATABASE_URL')) {
    fwrite(STDERR, "Refusing inherited database URL settings for local database actions.\n");
    exit(1);
}

try {
    $resolved = DB::connection()->getConfig();
    foreach (['driver', 'host', 'port', 'database', 'username'] as $key) {
        if ((string) $resolved[$key] !== (string) $connection[$key]) {
            throw new RuntimeException('Resolved database target differs from guarded configuration.');
        }
    }
    $identity = DB::selectOne('select current_database() as database, current_user as username');
    $role = DB::selectOne('select rolsuper, rolcreatedb, rolcreaterole from pg_roles where rolname = current_user');
    if ($identity->database !== $expected || $identity->username !== $expected
        || $role->rolsuper || $role->rolcreatedb || $role->rolcreaterole) {
        throw new RuntimeException('Unsafe database identity or privileges.');
    }

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

    echo 'Verified isolated '.$expected.' connection; PostgreSQL '.DB::selectOne('show server_version')->server_version.".\n";
} catch (Throwable $exception) {
    // Do not print credentials, connection strings or configuration in failure output.
    fwrite(STDERR, "Database smoke/reset failed; confirm local services and the documented target.\n");
    exit(1);
}
