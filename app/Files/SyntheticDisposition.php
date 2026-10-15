<?php

namespace App\Files;

/** Explicit test-scope authority; an environment switch alone is insufficient. */
final class SyntheticDisposition
{
    private function __construct(public readonly string $attempt, public readonly array $evidence) {}

    public static function authorize(object $attempt, string $source, string $reason): self
    {
        $project = (string) getenv('CETAKIN_DEV_PROJECT');
        $bucket = (string) getenv('CETAKIN_OBJECT_TEST_BUCKET');
        $endpoint = (string) getenv('CETAKIN_OBJECT_TEST_ENDPOINT');
        $settings = config('filesystems.design_objects.s3');
        $parts = explode('/', $attempt->object_namespace, 2);
        if (! app()->environment('testing') || getenv('CETAKIN_DEV_CONTAINER') !== '1' || getenv('CETAKIN_OBJECT_TEST_DISPOSABLE') !== '1'
            || ! preg_match('/^cetakin-([a-f0-9]{12})$/D', $project, $match) || $bucket !== 'cetakin-test-'.$match[1]
            || $endpoint !== 'http://objectstore:8333' || ($settings['endpoint'] ?? null) !== $endpoint
            || $attempt->object_driver !== 's3' || count($parts) !== 2 || $parts[0] !== $bucket
            || ! preg_match('#^synthetic-tests/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$#D', $parts[1])
            || ! hash_equals($attempt->object_endpoint, hash('sha256', $endpoint)) || trim($source) === '' || trim($reason) === ''
            || strlen($source) > 200 || strlen($reason) > 1000) {
            throw new ObjectUnavailable('REJECTED');
        }

        return new self($attempt->id, ['kind' => 'DISPOSABLE_SYNTHETIC', 'source' => $source, 'reason' => $reason,
            'scope' => $attempt->object_namespace, 'affected_actor' => $attempt->actor_id, 'customer' => $attempt->customer_id]);
    }
}
