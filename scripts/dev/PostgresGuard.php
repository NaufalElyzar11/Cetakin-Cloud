<?php

namespace Cetakin\Development;

use Illuminate\Database\Connection;
use Illuminate\Foundation\Application;
use RuntimeException;

// Shared by local infrastructure commands and PostgreSQL foundation tests.
final class PostgresGuard
{
    public static function beforeBootstrap(Application $app): void
    {
        if ($app->configurationIsCached()) {
            throw new RuntimeException('Refusing cached database configuration.');
        }
    }

    public static function connection(Application $app, bool $test): Connection
    {
        if ($app->configurationIsCached() || is_file($app->getCachedConfigPath())) {
            throw new RuntimeException('Refusing cached database configuration.');
        }

        $expected = $test ? 'cetakin_test' : 'cetakin_dev';
        $environment = $test ? 'testing' : 'local';
        $configured = $app['config']->get('database.connections.pgsql', []);
        if (getenv('CETAKIN_DEV_CONTAINER') !== '1'
            || ! preg_match('/^cetakin-[a-f0-9]{12}$/', getenv('CETAKIN_DEV_PROJECT') ?: '')
            || getenv('APP_ENV') !== $environment
            || ! $app->environment($environment)
            || $app['config']->get('database.default') !== 'pgsql'
            || ($configured['driver'] ?? null) !== 'pgsql'
            || ($configured['host'] ?? null) !== 'postgres'
            || (string) ($configured['port'] ?? '') !== '5432'
            || ($configured['database'] ?? null) !== $expected
            || ($configured['username'] ?? null) !== $expected
        ) {
            throw new RuntimeException('Refusing database action outside the isolated local target.');
        }

        foreach (['url', 'read', 'write', 'pool', 'pooled', 'direct'] as $alternateTarget) {
            if (! empty($configured[$alternateTarget])) {
                throw new RuntimeException('Refusing alternative database targeting configuration.');
            }
        }
        if (getenv('DB_URL') || getenv('DATABASE_URL')) {
            throw new RuntimeException('Refusing inherited database URL settings.');
        }

        $connection = $app['db']->connection('pgsql');
        $resolved = $connection->getConfig();
        foreach (['driver', 'host', 'port', 'database', 'username'] as $key) {
            if ((string) ($resolved[$key] ?? '') !== (string) $configured[$key]) {
                throw new RuntimeException('Resolved database target differs from guarded configuration.');
            }
        }

        $identity = $connection->selectOne('select current_database() as database, current_user as username');
        $role = $connection->selectOne('select rolsuper, rolcreatedb, rolcreaterole from pg_roles where rolname = current_user');
        if ($identity->database !== $expected || $identity->username !== $expected
            || $role->rolsuper || $role->rolcreatedb || $role->rolcreaterole) {
            throw new RuntimeException('Unsafe database identity or privileges.');
        }

        return $connection;
    }
}
