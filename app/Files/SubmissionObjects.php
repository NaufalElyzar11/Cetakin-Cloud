<?php

namespace App\Files;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/** Durable accounting prevents a maintenance claim from guessing a writer stopped. */
final class SubmissionObjects
{
    /** @param list<array{id:string,name:string,format:string,size:int,digest:string}> $manifest
     * @param  list<array{name:string,format:string,size:int,digest:string,path:string}>  $uploads
     */
    public function publish(object $attempt, ExactDesignObjects $objects, array $manifest, array $uploads): void
    {
        $token = $this->begin($attempt->id);
        try {
            foreach ($manifest as $index => $file) {
                $objects->publish($attempt->id, $file, $uploads[$index]['path']);
            }
            foreach ($manifest as $file) {
                $objects->read($attempt->id, $file)->close();
            }
            $this->settle($token, 'SUCCEEDED');
        } catch (Throwable $exception) {
            // A timeout/crash never becomes "not written" based on age or an
            // absent read. The durable UNKNOWN/IN_FLIGHT token protects it.
            $this->settle($token, $exception instanceof ObjectUnavailable && $exception->outcome === 'REJECTED' ? 'REJECTED' : 'UNKNOWN');
            throw $exception;
        }
    }

    public function begin(string $attempt): string
    {
        return DB::transaction(function () use ($attempt): string {
            $guard = DB::table('submission_object_guards')->where('attempt_id', $attempt)->lockForUpdate()->first();
            if ($guard === null || $guard->state !== 'OPEN') {
                throw new ObjectUnavailable('REJECTED');
            }
            $id = (string) Str::uuid();
            DB::table('submission_object_publications')->insert(['id' => $id, 'attempt_id' => $attempt, 'outcome' => 'IN_FLIGHT', 'started_at' => now()]);

            return $id;
        });
    }

    public function settle(string $token, string $outcome): void
    {
        DB::transaction(function () use ($token, $outcome): void {
            DB::table('submission_object_publications')->where('id', $token)->update(['outcome' => $outcome, 'settled_at' => now()]);
        });
    }
}
