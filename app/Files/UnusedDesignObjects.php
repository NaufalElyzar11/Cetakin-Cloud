<?php

namespace App\Files;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** No operational deletion route, sweep, expiry or retention policy. */
final class UnusedDesignObjects
{
    public function classify(SyntheticDisposition $disposition): void
    {
        DB::transaction(function () use ($disposition): void {
            $guard = DB::table('submission_object_guards')->where('attempt_id', $disposition->attempt)->lockForUpdate()->first();
            if ($guard === null || $guard->state !== 'OPEN') {
                throw new ObjectUnavailable('REJECTED');
            }
            // PostgreSQL repeats the confirmed/in-flight/unknown checks under
            // the same guard, so direct association cannot bypass this claim.
            DB::table('submission_object_guards')->where('attempt_id', $disposition->attempt)->update([
                'state' => 'UNUSED', 'disposition' => json_encode($disposition->evidence, JSON_THROW_ON_ERROR), 'disposed_at' => now(),
            ]);
        });
    }

    public function claim(SyntheticDisposition $disposition): string
    {
        return DB::transaction(function () use ($disposition): string {
            $guard = DB::table('submission_object_guards')->where('attempt_id', $disposition->attempt)->lockForUpdate()->first();
            if ($guard === null || ! in_array($guard->state, ['UNUSED', 'DELETE_CLAIMED'], true)
                || json_decode($guard->disposition, true, flags: JSON_THROW_ON_ERROR) != $disposition->evidence) {
                throw new ObjectUnavailable('REJECTED');
            }
            if ($guard->state === 'DELETE_CLAIMED') {
                return $guard->deletion_claim;
            }
            $claim = (string) Str::uuid();
            DB::table('submission_object_guards')->where('attempt_id', $disposition->attempt)->update(['state' => 'DELETE_CLAIMED', 'deletion_claim' => $claim, 'claimed_at' => now()]);

            return $claim;
        });
    }

    public function remove(SyntheticDisposition $disposition, string $claim): void
    {
        $attempt = DB::table('submission_attempts')->where('id', $disposition->attempt)->first();
        if ($attempt === null) {
            throw new ObjectUnavailable('REJECTED');
        }
        // Re-evaluate the restricted environment, never trust a saved permit
        // after configuration changed or a process moved to another scope.
        SyntheticDisposition::authorize($attempt, $disposition->evidence['source'], $disposition->evidence['reason']);
        $guard = DB::table('submission_object_guards')->where('attempt_id', $attempt->id)->first();
        if ($guard === null || $guard->state !== 'DELETE_CLAIMED' || $guard->deletion_claim !== $claim) {
            throw new ObjectUnavailable('REJECTED');
        }
        $objects = app(DesignObjectStores::class)->forAttempt($attempt);
        $manifest = json_decode($attempt->manifest, true, flags: JSON_THROW_ON_ERROR);
        foreach ($manifest as $file) {
            $objects->remove($attempt->id, $file['id']);
            if (! $objects->absent($attempt->id, $file['id'])) {
                throw new ObjectUnavailable('UNKNOWN');
            }
        }
        DB::transaction(function () use ($attempt, $claim): void {
            $guard = DB::table('submission_object_guards')->where('attempt_id', $attempt->id)->lockForUpdate()->first();
            if ($guard->state === 'DELETED' && $guard->deletion_claim === $claim) {
                return;
            }
            if ($guard->state !== 'DELETE_CLAIMED' || $guard->deletion_claim !== $claim) {
                throw new ObjectUnavailable('REJECTED');
            }
            DB::table('submission_object_guards')->where('attempt_id', $attempt->id)->update(['state' => 'DELETED', 'deleted_at' => now()]);
        });
    }
}
