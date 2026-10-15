<?php

namespace App\Files;

use RuntimeException;

final class ObjectUnavailable extends RuntimeException
{
    public function __construct(public readonly string $outcome)
    {
        parent::__construct('Exact private object outcome: '.$outcome.'.');
    }
}
