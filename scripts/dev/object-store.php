<?php

declare(strict_types=1);

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';

// Narrow local synthetic capability probe, not a customer retention/deletion tool.
$project = getenv('CETAKIN_DEV_PROJECT') ?: '';
$action = $argv[1] ?? '';
if (getenv('CETAKIN_DEV_CONTAINER') !== '1'
    || ! preg_match('/^cetakin-([a-f0-9]{12})$/D', $project, $matches)
    || ! in_array($action, ['init', 'check'], true)) {
    fwrite(STDERR, "Object checks require this checkout's local development container.\n");
    exit(1);
}

$devBucket = 'cetakin-dev-'.$matches[1];
$testBucket = 'cetakin-test-'.$matches[1];
$client = static fn (string $identity): S3Client => new S3Client([
    'version' => '2006-03-01',
    'region' => 'us-east-1',
    'endpoint' => 'http://objectstore:8333',
    'use_path_style_endpoint' => true,
    'credentials' => ['key' => $project.'-'.$identity, 'secret' => $project.'-'.$identity.'-synthetic-only'],
    'retries' => 0,
    'http' => ['connect_timeout' => 3, 'timeout' => 15],
]);
$expectStatus = static function (callable $operation, int $status): void {
    try {
        $operation();
    } catch (AwsException $failure) {
        if ($failure->getStatusCode() === $status) {
            return;
        }
        throw new RuntimeException('Compatible object capability returned an unexpected outcome.');
    }
    throw new RuntimeException('Compatible object capability was not enforced.');
};

try {
    $stage = 'bucket initialization';
    if ($action === 'init') {
        // This transient identity is never injected into the application runtime.
        $bootstrap = $client('bootstrap');
        foreach ([$devBucket, $testBucket] as $bucket) {
            try {
                $bootstrap->headBucket(['Bucket' => $bucket]);
            } catch (AwsException $failure) {
                if ($failure->getStatusCode() !== 404) {
                    throw $failure;
                }
                $bootstrap->createBucket(['Bucket' => $bucket]);
            }
        }
        $bootstrap->putBucketPolicy(['Bucket' => $devBucket, 'Policy' => json_encode([
            'Version' => '2012-10-17',
            'Statement' => [[
                'Effect' => 'Deny', 'Principal' => '*',
                'Action' => ['s3:DeleteObject', 's3:DeleteObjectVersion'],
                'Resource' => 'arn:aws:s3:::'.$devBucket.'/*',
            ]],
        ], JSON_THROW_ON_ERROR)]);
        echo "Private project dev/test buckets initialized; development object deletion denied.\n";
    }

    $test = $client('test');
    $dev = $client('dev');
    $readonly = $client('readonly');
    $stage = 'bucket access';
    $dev->headBucket(['Bucket' => $devBucket]);
    $test->headBucket(['Bucket' => $testBucket]);
    $expectStatus(fn () => $test->headBucket(['Bucket' => $devBucket]), 403);
    $expectStatus(fn () => $dev->headBucket(['Bucket' => $testBucket]), 403);
    $stage = 'development deletion denial';
    $expectStatus(fn () => $dev->deleteObject(['Bucket' => $devBucket, 'Key' => 'synthetic-delete-denial-probe']), 403);

    $key = 'synthetic-tests/'.Str::uuid().'/capability';
    $bytes = 'CET-007 disposable synthetic capability fixture';
    $stage = 'conditional fixture publication';
    $test->putObject(['Bucket' => $testBucket, 'Key' => $key, 'Body' => $bytes, 'IfNoneMatch' => '*']);
    try {
        $actual = (string) $test->getObject(['Bucket' => $testBucket, 'Key' => $key])['Body'];
        if (strlen($actual) !== strlen($bytes) || hash('sha256', $actual) !== hash('sha256', $bytes)) {
            throw new RuntimeException('Compatible object bytes did not match.');
        }
        $stage = 'conditional overwrite denial';
        $expectStatus(fn () => $test->putObject(['Bucket' => $testBucket, 'Key' => $key, 'Body' => 'changed', 'IfNoneMatch' => '*']), 412);
        $stage = 'read-only write denial';
        $expectStatus(fn () => $readonly->putObject(['Bucket' => $testBucket, 'Key' => $key, 'Body' => 'changed', 'IfNoneMatch' => '*']), 403);
        $anonymous = new S3Client([
            'version' => '2006-03-01', 'region' => 'us-east-1',
            'endpoint' => 'http://objectstore:8333', 'use_path_style_endpoint' => true,
            'credentials' => false, 'retries' => 0,
            'http' => ['connect_timeout' => 3, 'timeout' => 15],
        ]);
        $stage = 'anonymous read denial';
        $expectStatus(fn () => $anonymous->getObject(['Bucket' => $testBucket, 'Key' => $key]), 403);
    } finally {
        // Exact UUID probe created in this process; disposable test bucket only.
        $test->deleteObject(['Bucket' => $testBucket, 'Key' => $key]);
    }
    $expectStatus(fn () => $test->headObject(['Bucket' => $testBucket, 'Key' => $key]), 404);
    echo "Compatible storage: private access, scoped credentials, conditional non-overwrite, exact SHA-256 and disposable probe cleanup passed.\n";
} catch (Throwable $failure) {
    // Never disclose credential-bearing SDK request details or raw provider errors.
    fwrite(STDERR, 'Compatible object check failed during '.$stage.". Inspect the local service configuration and availability.\n");
    if ($failure instanceof AwsException) {
        fwrite(STDERR, 'Provider HTTP status: '.($failure->getStatusCode() ?? 'unresolved').".\n");
    }
    exit(1);
}
