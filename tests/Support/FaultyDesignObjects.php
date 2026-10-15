<?php

namespace Tests\Support;

use App\Files\ExactDesignObjects;
use App\Files\ObjectUnavailable;
use App\Files\VerifiedDesignRead;
use Closure;

/** Faults bracket real adapter operations; real service semantics remain tested separately. */
final class FaultyDesignObjects implements ExactDesignObjects
{
    public function __construct(private ExactDesignObjects $real, private string $fault = '', private ?Closure $barrier = null) {}

    public function publish(string $attempt, array $file, string $source): void
    {
        if ($this->fault === 'before-write') {
            throw new ObjectUnavailable('REJECTED');
        }
        $this->real->publish($attempt, $file, $source);
        if ($this->barrier !== null) {
            ($this->barrier)();
        }
        if ($this->fault === 'after-write') {
            throw new ObjectUnavailable('UNKNOWN');
        }
    }

    public function read(string $attempt, array $file): VerifiedDesignRead
    {
        if ($this->fault === 'read-unknown') {
            throw new ObjectUnavailable('UNKNOWN');
        }

        return $this->real->read($attempt, $file);
    }

    public function remove(string $attempt, string $file): void
    {
        $this->real->remove($attempt, $file);
        if ($this->fault === 'after-delete') {
            throw new ObjectUnavailable('UNKNOWN');
        }
    }

    public function absent(string $attempt, string $file): bool
    {
        if ($this->fault === 'read-unknown') {
            throw new ObjectUnavailable('UNKNOWN');
        }

        return $this->real->absent($attempt, $file);
    }
}
