<?php

namespace App\Files;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Local-only, immutable publication. Object adapters/reconciliation are CET-007. */
class PrivateDesignFiles
{
    public function path(string $attempt, string $file): string
    {
        foreach ([$attempt, $file] as $identity) {
            if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $identity)) {
                throw new RuntimeException('Invalid internal design identity.');
            }
        }

        return Storage::disk('local')->path('intake/'.$attempt.'/'.$file);
    }

    /** @param array{id:string,size:int,digest:string} $file */
    public function publish(string $attempt, array $file, string $source): void
    {
        $final = $this->path($attempt, $file['id']);
        $directory = dirname($final);
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Private file publication failed.');
        }
        if (is_file($final)) {
            $this->verify($final, $file['size'], $file['digest']);

            return;
        }
        // A complete staging file is never a confirmed version. A hard link
        // publishes the complete inode atomically, refusing an existing name.
        $temporary = $directory.'/.writing-'.bin2hex(random_bytes(16));
        $input = @fopen($source, 'rb');
        $output = @fopen($temporary, 'x+b');
        try {
            if ($input === false || $output === false) {
                throw new RuntimeException('Private file publication failed.');
            }
            chmod($temporary, 0600);
            $bytes = stream_copy_to_stream($input, $output, $file['size'] + 1);
            if ($bytes !== $file['size'] || ! fflush($output) || ! fsync($output)) {
                throw new RuntimeException('Private file publication did not complete.');
            }
            $this->verify($temporary, $file['size'], $file['digest']);
            if (! @link($temporary, $final) && ! is_file($final)) {
                throw new RuntimeException('Private file publication failed.');
            }
            $this->verify($final, $file['size'], $file['digest']);
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            // Only this process's unfinished technical staging name; never
            // uncertain published bytes or historical design versions.
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    public function verify(string $path, int $size, string $digest): void
    {
        clearstatcache(true, $path);
        if (! is_file($path) || is_link($path) || filesize($path) !== $size || hash_file('sha256', $path) !== $digest) {
            throw new RuntimeException('The exact private file is unavailable or its integrity cannot be confirmed.');
        }
    }
}
