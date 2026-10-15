<?php

namespace App\Files;

use Aws\S3\S3Client;

class DesignObjectStores
{
    public function selectedIdentity(): array
    {
        $driver = config('filesystems.design_objects.driver', 'local');
        if ($driver === 'local') {
            return ['object_driver' => 'local', 'object_namespace' => 'local', 'object_endpoint' => str_repeat('0', 64)];
        }
        if ($driver !== 's3') {
            throw new ObjectUnavailable('REJECTED');
        }
        $settings = config('filesystems.design_objects.s3');

        return ['object_driver' => 's3', 'object_namespace' => $settings['bucket'].'/'.$settings['prefix'], 'object_endpoint' => hash('sha256', rtrim($settings['endpoint'], '/'))];
    }

    public function forAttempt(object $attempt): ExactDesignObjects
    {
        if ($attempt->object_driver === 'local' && $attempt->object_namespace === 'local') {
            return app(PrivateDesignFiles::class);
        }
        $settings = config('filesystems.design_objects.s3');
        $namespace = explode('/', $attempt->object_namespace, 2);
        if ($attempt->object_driver !== 's3' || count($namespace) !== 2 || $namespace[0] !== ($settings['bucket'] ?? null)
            || ! hash_equals($attempt->object_endpoint, hash('sha256', rtrim($settings['endpoint'] ?? '', '/')))) {
            throw new ObjectUnavailable('UNKNOWN');
        }

        return new CompatibleDesignObjects($this->client($settings), $namespace[0], $namespace[1]);
    }

    public function client(?array $settings = null): S3Client
    {
        $settings ??= config('filesystems.design_objects.s3');

        return new S3Client(['version' => '2006-03-01', 'region' => $settings['region'], 'endpoint' => $settings['endpoint'],
            'credentials' => ['key' => $settings['key'], 'secret' => $settings['secret']], 'use_path_style_endpoint' => true,
            'retries' => 0, 'http' => ['connect_timeout' => 3, 'timeout' => 20, 'read_timeout' => 5]]);
    }
}
