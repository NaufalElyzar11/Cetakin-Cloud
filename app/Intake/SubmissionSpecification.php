<?php

namespace App\Intake;

use Illuminate\Validation\ValidationException;

final class SubmissionSpecification
{
    public const COLORS = ['orange', 'sky blue', 'white', 'black'];

    public static function quantity(mixed $input): string
    {
        if ((! is_string($input) && ! is_int($input)) || ! preg_match('/^[1-9][0-9]*$/D', (string) $input)) {
            throw ValidationException::withMessages(['quantity' => 'Enter a positive whole number of complete products or sets.']);
        }
        $value = (string) $input;
        // PostgreSQL bigint representation, not an approved business maximum.
        if (strlen($value) > 19 || (strlen($value) === 19 && strcmp($value, '9223372036854775807') > 0)) {
            throw ValidationException::withMessages(['quantity' => 'This quantity cannot be represented safely.']);
        }

        return $value;
    }
}
