<?php

namespace Tests\Database\Files;

use App\Files\DesignObjectStores;
use Symfony\Component\Process\Process;
use Tests\Support\PostgresTestCase;

class TestStorageIsolationTest extends PostgresTestCase
{
    public function test_inherited_development_s3_driver_cannot_redirect_normal_test_objects_to_development_bucket(): void
    {
        $this->assertSame('local', app(DesignObjectStores::class)->selectedIdentity()['object_driver']);
        $probe = <<<'PHP'
require 'vendor/autoload.php';
$harness = new class('testSynthetic') extends \Tests\Support\PostgresTestCase {
    public function testSynthetic(): void {}
};
$app = $harness->createApplication();
echo json_encode([$app->make(\App\Files\DesignObjectStores::class)->selectedIdentity(), $app['config']->get('filesystems.disks.local.root')], JSON_THROW_ON_ERROR);
PHP;
        $worker = new Process([PHP_BINARY, '-r', $probe], base_path(), ['DESIGN_OBJECT_DRIVER' => 's3',
            'CETAKIN_S3_BUCKET' => 'synthetic-development-bucket-never-use', 'CETAKIN_S3_KEY' => 'synthetic-development-key-never-use', 'CETAKIN_S3_SECRET' => 'synthetic-development-secret-never-use']);
        $worker->mustRun();
        [$identity, $root] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['object_driver' => 'local', 'object_namespace' => 'local', 'object_endpoint' => str_repeat('0', 64)], $identity);
        $this->assertStringStartsWith(storage_path('app/private/tests/'), $root);
        $this->assertNotSame($this->privateTestRoot, $root);
    }
}
