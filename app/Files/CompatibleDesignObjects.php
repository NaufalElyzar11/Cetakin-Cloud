<?php

namespace App\Files;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;

/** Tested S3 conditional creation; no generic Storage::put overwrite semantics. */
class CompatibleDesignObjects implements ExactDesignObjects
{
    public function __construct(private S3Client $client, private string $bucket, private string $prefix) {}

    public function key(string $attempt, string $file): string
    {
        foreach ([$attempt, $file] as $id) {
            if (! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $id)) {
                throw new ObjectUnavailable('REJECTED');
            }
        }
        if (! preg_match('#^(?:[a-z0-9][a-z0-9-]*/)*[a-z0-9][a-z0-9-]*$#D', $this->prefix)) {
            throw new ObjectUnavailable('REJECTED');
        }

        return $this->prefix.'/intake/'.$attempt.'/'.$file;
    }

    public function publish(string $attempt, array $file, string $source): void
    {
        $input = @fopen($source, 'rb');
        if ($input === false) {
            throw new ObjectUnavailable('REJECTED');
        }
        try {
            $verified = VerifiedDesignRead::copy($input, $file['size'], $file['digest']);
        } finally {
            fclose($input);
        }
        try {
            try {
                $this->client->putObject(['Bucket' => $this->bucket, 'Key' => $this->key($attempt, $file['id']),
                    'Body' => $verified->stream(), 'ContentLength' => $file['size'], 'ContentType' => 'application/octet-stream',
                    'IfNoneMatch' => '*']);
            } catch (AwsException $exception) {
                if ($exception->getStatusCode() !== 412) {
                    throw new ObjectUnavailable($exception->getStatusCode() === 403 ? 'REJECTED' : 'UNKNOWN');
                }
                // A 412 may be a concurrent identical write OR different bytes.
                // Only independent size/SHA-256 verification may reuse content.
            }
            $this->read($attempt, $file)->close();
        } catch (ObjectUnavailable $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new ObjectUnavailable('UNKNOWN');
        } finally {
            $verified->close();
        }
    }

    public function read(string $attempt, array $file): VerifiedDesignRead
    {
        try {
            $result = $this->client->getObject(['Bucket' => $this->bucket, 'Key' => $this->key($attempt, $file['id']), '@http' => ['stream' => true]]);
            if ((int) $result['ContentLength'] !== $file['size']) {
                $result['Body']->close();
                throw new ObjectUnavailable('CORRUPT');
            }
            $input = $result['Body']->detach();
            try {
                return VerifiedDesignRead::copy($input, $file['size'], $file['digest']);
            } finally {
                if (is_resource($input)) {
                    fclose($input);
                }
            }
        } catch (AwsException $exception) {
            throw new ObjectUnavailable($exception->getStatusCode() === 404 && $exception->getAwsErrorCode() === 'NoSuchKey' ? 'MISSING' : 'UNKNOWN');
        } catch (ObjectUnavailable $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new ObjectUnavailable('UNKNOWN');
        }
    }

    public function remove(string $attempt, string $file): void
    {
        try {
            $this->client->deleteObject(['Bucket' => $this->bucket, 'Key' => $this->key($attempt, $file)]);
        } catch (\Throwable $exception) {
            throw new ObjectUnavailable('UNKNOWN');
        }
    }

    public function absent(string $attempt, string $file): bool
    {
        try {
            $result = $this->client->getObject(['Bucket' => $this->bucket, 'Key' => $this->key($attempt, $file), '@http' => ['stream' => true]]);
            $result['Body']->close();

            return false;
        } catch (AwsException $exception) {
            if ($exception->getStatusCode() === 404 && $exception->getAwsErrorCode() === 'NoSuchKey') {
                return true;
            }
            throw new ObjectUnavailable('UNKNOWN');
        } catch (\Throwable $exception) {
            throw new ObjectUnavailable('UNKNOWN');
        }
    }
}
