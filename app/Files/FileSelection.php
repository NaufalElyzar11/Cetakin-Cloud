<?php

namespace App\Files;

use Illuminate\Validation\ValidationException;

final class FileSelection
{
    public const MAX_FILE_BYTES = 26_214_400;

    public const MAX_TOTAL_BYTES = 78_643_200;

    public const MAX_FILES = 5;

    /** @param list<int> $sizes */
    public static function validateSizes(array $sizes): void
    {
        if (count($sizes) < 1 || count($sizes) > self::MAX_FILES) {
            throw ValidationException::withMessages(['files' => 'Select between one and five design files for the same product or component set.']);
        }
        $total = 0;
        foreach ($sizes as $size) {
            if ($size < 1 || $size > self::MAX_FILE_BYTES) {
                throw ValidationException::withMessages(['files' => 'Each complete file must be at most 25 MiB.']);
            }
            $total += $size;
        }
        if ($total > self::MAX_TOTAL_BYTES) {
            throw ValidationException::withMessages(['files' => 'The selected files must total at most 75 MiB.']);
        }
    }
}
