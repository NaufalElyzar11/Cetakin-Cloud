<?php

namespace Tests\Support;

use App\Files\DesignObjectStores;
use Aws\S3\S3Client;
use Illuminate\Support\Str;
use RuntimeException;

trait CompatibleObjects
{
    protected string $objectPrefix;

    protected array $objectSettings;

    protected S3Client $objectClient;

    protected function configureObjects(): void
    {
        $project = getenv('CETAKIN_DEV_PROJECT');
        $bucket = getenv('CETAKIN_OBJECT_TEST_BUCKET');
        if (! app()->environment('testing') || getenv('CETAKIN_DEV_CONTAINER') !== '1'
            || ! preg_match('/^cetakin-([a-f0-9]{12})$/D', (string) $project, $match)
            || $bucket !== 'cetakin-test-'.$match[1] || getenv('CETAKIN_OBJECT_TEST_ENDPOINT') !== 'http://objectstore:8333'
            || getenv('CETAKIN_OBJECT_TEST_DISPOSABLE') !== '1') {
            throw new RuntimeException('Real isolated compatible object tests require the approved synthetic environment; they cannot silently skip.');
        }
        $this->objectPrefix = 'synthetic-tests/'.Str::uuid();
        $this->objectSettings = ['endpoint' => getenv('CETAKIN_OBJECT_TEST_ENDPOINT'), 'bucket' => $bucket,
            'key' => getenv('CETAKIN_OBJECT_TEST_KEY'), 'secret' => getenv('CETAKIN_OBJECT_TEST_SECRET'),
            'region' => getenv('CETAKIN_OBJECT_TEST_REGION'), 'prefix' => $this->objectPrefix];
        config(['filesystems.design_objects' => ['driver' => 's3', 's3' => $this->objectSettings]]);
        $this->objectClient = app(DesignObjectStores::class)->client($this->objectSettings);
        $this->objectClient->headBucket(['Bucket' => $bucket]);
    }

    protected function removeSyntheticObjects(): void
    {
        if (! isset($this->objectClient, $this->objectPrefix)) {
            return;
        }
        // Test teardown only, after workers are joined: own random fixture
        // prefix, exact worktree test bucket, no application or other prefix.
        do {
            $page = $this->objectClient->listObjectsV2(['Bucket' => $this->objectSettings['bucket'], 'Prefix' => $this->objectPrefix.'/']);
            foreach ($page['Contents'] ?? [] as $item) {
                if (! str_starts_with($item['Key'], $this->objectPrefix.'/')) {
                    throw new RuntimeException('Synthetic cleanup scope mismatch.');
                }
                $this->objectClient->deleteObject(['Bucket' => $this->objectSettings['bucket'], 'Key' => $item['Key']]);
            }
        } while ($page['IsTruncated']);
    }
}
