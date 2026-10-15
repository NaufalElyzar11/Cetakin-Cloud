<?php

namespace App\Files;

/** Bounded, verified spool: nothing reaches a customer before full digest verification. */
final class VerifiedDesignRead
{
    /** @param resource $stream */
    private function __construct(private $stream) {}

    /** @param resource $input */
    public static function copy($input, int $size, string $digest): self
    {
        if (! is_resource($input) || $size < 1 || $size > FileSelection::MAX_FILE_BYTES || ! preg_match('/^[a-f0-9]{64}$/D', $digest)) {
            throw new ObjectUnavailable('CORRUPT');
        }
        $output = fopen('php://temp/maxmemory:2097152', 'w+b');
        if ($output === false) {
            throw new ObjectUnavailable('UNKNOWN');
        }
        $hash = hash_init('sha256');
        $total = 0;
        $deadline = hrtime(true) + 20_000_000_000;
        try {
            while (! feof($input) && $total <= $size) {
                if (hrtime(true) > $deadline) {
                    throw new ObjectUnavailable('UNKNOWN');
                }
                $part = fread($input, min(65536, $size + 1 - $total));
                if ($part === false || ($part === '' && ! feof($input))) {
                    throw new ObjectUnavailable('UNKNOWN');
                }
                $total += strlen($part);
                hash_update($hash, $part);
                if (fwrite($output, $part) !== strlen($part)) {
                    throw new ObjectUnavailable('UNKNOWN');
                }
            }
            if (hrtime(true) > $deadline) {
                throw new ObjectUnavailable('UNKNOWN');
            }
            if ($total !== $size || ! hash_equals($digest, hash_final($hash))) {
                throw new ObjectUnavailable('CORRUPT');
            }
            rewind($output);

            return new self($output);
        } catch (\Throwable $exception) {
            fclose($output);
            throw $exception;
        }
    }

    /** @return resource */
    public function stream()
    {
        rewind($this->stream);

        return $this->stream;
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
