<?php

namespace Tests\Support;

use Cetakin\Development\PostgresGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase;

require_once dirname(__DIR__, 2).'/scripts/dev/PostgresGuard.php';

abstract class PostgresTestCase extends TestCase
{
    protected Connection $postgres;

    protected string $privateTestRoot;

    public function createApplication(): Application
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        PostgresGuard::beforeBootstrap($app);
        $app->make(Kernel::class)->bootstrap();
        $this->postgres = PostgresGuard::connection($app, true);

        // The private volume belongs to this worktree; each test has its own
        // root. Never reuse application designs or a public disk.
        $this->privateTestRoot = $app->storagePath('app/private/tests/'.getmypid().'-'.bin2hex(random_bytes(8)));
        $app['config']->set('filesystems.disks.local.root', $this->privateTestRoot);

        return $app;
    }
}
