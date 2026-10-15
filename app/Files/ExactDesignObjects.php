<?php

namespace App\Files;

/** Only immutable exact design objects, not a general filesystem API. */
interface ExactDesignObjects
{
    /** @param array{id:string,size:int,digest:string} $file */
    public function publish(string $attempt, array $file, string $source): void;

    /** @param array{id:string,size:int,digest:string} $file */
    public function read(string $attempt, array $file): VerifiedDesignRead;

    /** Removal is invoked only after durable, synthetic disposition guards. */
    public function remove(string $attempt, string $file): void;

    /** Missing is distinct from provider uncertainty/denied access. */
    public function absent(string $attempt, string $file): bool;
}
