<?php

namespace Tests\Database;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PostgresTestCase;

class PostgresFoundationTest extends PostgresTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Guarded PostgreSQL is already resolved before any schema changes.
        Artisan::call('migrate', ['--force' => true]);
        config(['session.driver' => 'database', 'cache.default' => 'database']);
    }

    public function test_application_http_and_real_postgresql_are_available_together(): void
    {
        $this->getJson('/up')->assertOk()->assertExactJson(['status' => 'ok']);
        $identity = $this->postgres->selectOne('select current_database() as database, current_user as username');

        $this->assertSame('cetakin_test', $identity->database);
        $this->assertSame('cetakin_test', $identity->username);
        $this->assertSame('pgsql', $this->postgres->getDriverName());
    }

    public function test_postgresql_rolls_back_a_transaction_without_losing_earlier_data(): void
    {
        $this->postgres->statement('create temporary table cetakin_rollback_probe (value integer not null)');
        try {
            $this->postgres->insert('insert into cetakin_rollback_probe (value) values (?)', [1]);
            $this->postgres->beginTransaction();
            $this->postgres->insert('insert into cetakin_rollback_probe (value) values (?)', [2]);
            $this->postgres->rollBack();

            $values = $this->postgres->select('select value from cetakin_rollback_probe order by value');
            $this->assertSame([1], array_column($values, 'value'));
        } finally {
            while ($this->postgres->transactionLevel() > 0) {
                $this->postgres->rollBack();
            }
            $this->postgres->statement('drop table cetakin_rollback_probe');
        }
    }

    public function test_independent_connections_observe_only_committed_probe_data(): void
    {
        // Short-lived infrastructure probe, not an application migration/model.
        // Ordinary rollback isolation must not wrap future independent races.
        $table = 'cetakin_probe_'.bin2hex(random_bytes(8));
        $peer = $this->app['db']->connectUsing('foundation_peer', $this->postgres->getConfig(), true);
        $this->postgres->statement('create table '.$table.' (value integer not null)');

        try {
            $firstPid = $this->postgres->selectOne('select pg_backend_pid() as pid')->pid;
            $secondPid = $peer->selectOne('select pg_backend_pid() as pid')->pid;
            $this->assertNotSame($firstPid, $secondPid);
            $this->assertSame('cetakin_test', $peer->selectOne('select current_database() as database')->database);

            $this->postgres->beginTransaction();
            $this->postgres->insert('insert into '.$table.' (value) values (?)', [7]);
            $this->assertSame(0, (int) $peer->selectOne('select count(*) as count from '.$table)->count);
            $this->postgres->commit();
            $this->assertSame(7, $peer->selectOne('select value from '.$table)->value);
        } finally {
            while ($this->postgres->transactionLevel() > 0) {
                $this->postgres->rollBack();
            }
            $this->app['db']->purge('foundation_peer');
            $this->postgres->statement('drop table '.$table);
        }
    }

    public function test_private_test_files_use_a_unique_root_without_public_serving(): void
    {
        $disk = Storage::disk('local');
        $path = 'foundation.txt';

        try {
            $this->assertStringStartsWith(storage_path('app/private/tests/'), $this->privateTestRoot);
            $this->assertFalse(config('filesystems.disks.local.serve'));
            $this->assertFalse($disk->exists($path));
            $this->assertTrue($disk->put($path, 'synthetic foundation fixture'));
            $this->assertSame('synthetic foundation fixture', $disk->get($path));
            $this->assertSame($this->privateTestRoot.'/'.$path, $disk->path($path));
        } finally {
            $disk->delete($path);
            if (is_dir($this->privateTestRoot)) {
                rmdir($this->privateTestRoot);
            }
        }
    }
}
