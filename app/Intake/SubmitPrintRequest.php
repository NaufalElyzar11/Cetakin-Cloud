<?php

namespace App\Intake;

use App\Access\Models\User;
use App\Files\DesignObjectStores;
use App\Files\ObjectUnavailable;
use App\Files\SubmissionObjects;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class SubmitPrintRequest
{
    public function __construct(private DesignObjectStores $stores, private SubmissionObjects $objects) {}

    /**
     * @param  array{material:string,color:string,quantity:string,notes:?string}  $specification
     * @param  list<array{name:string,format:string,size:int,digest:string,path:string}>  $uploads
     */
    public function submit(User $actor, int $customer, string $operation, array $specification, array $uploads): string
    {
        $descriptors = array_map(fn (array $file) => array_diff_key($file, ['path' => true]), $uploads);
        $payload = hash('sha256', json_encode(['customer' => $customer, 'specification' => $specification, 'files' => $descriptors], JSON_THROW_ON_ERROR));
        $attempt = DB::transaction(function () use ($actor, $customer, $operation, $payload, $specification, $descriptors): object {
            $this->authorize($actor->id, $customer);
            $manifest = array_map(fn (array $file) => ['id' => (string) Str::uuid(), ...$file], $descriptors);
            DB::table('submission_attempts')->insertOrIgnore([
                'id' => (string) Str::uuid(), 'actor_id' => $actor->id, 'customer_id' => $customer,
                'operation_id' => $operation, 'payload_digest' => $payload,
                'specification' => json_encode($specification, JSON_THROW_ON_ERROR),
                'manifest' => json_encode($manifest, JSON_THROW_ON_ERROR), 'created_at' => now(),
                ...$this->stores->selectedIdentity(),
            ]);
            $attempt = DB::table('submission_attempts')->where('actor_id', $actor->id)->where('operation_id', $operation)->first();
            if ($attempt === null || $attempt->customer_id !== $customer || ! hash_equals($attempt->payload_digest, $payload)) {
                throw ValidationException::withMessages(['operation_id' => 'This attempt is already bound to different files or specifications. Start a new attempt for changed work.']);
            }

            return $attempt;
        });
        if ($attempt->request_id !== null) {
            return $attempt->request_id;
        }
        $manifest = json_decode($attempt->manifest, true, flags: JSON_THROW_ON_ERROR);
        // File I/O is deliberately outside the short PostgreSQL locks. Durable
        // intent protects the same exact objects through write/commit failures.
        $this->objects->publish($attempt, $this->stores->forAttempt($attempt), $manifest, $uploads);

        return $this->confirm($actor->id, $customer, $attempt->id, $manifest, $specification);
    }

    /** Bounded same-intent recovery; only the original actor's current owned attempt. */
    public function reconcile(User $actor, string $operation): string
    {
        $attempt = DB::table('submission_attempts')->where('actor_id', $actor->id)->where('operation_id', $operation)->first();
        abort_if($attempt === null, 404);
        $relationship = DB::table('customer_user')->where('user_id', $actor->id)->where('customer_id', $attempt->customer_id)->whereNull('revoked_at')->exists();
        abort_unless($relationship, 404);
        if ($attempt->request_id !== null) {
            return $attempt->request_id;
        }
        $guard = DB::table('submission_object_guards')->where('attempt_id', $attempt->id)->first();
        if ($guard === null || $guard->state !== 'OPEN') {
            throw new ObjectUnavailable('REJECTED');
        }
        $manifest = json_decode($attempt->manifest, true, flags: JSON_THROW_ON_ERROR);
        $store = $this->stores->forAttempt($attempt);
        foreach ($manifest as $file) {
            $store->read($attempt->id, $file)->close();
        }

        return $this->confirm($actor->id, $attempt->customer_id, $attempt->id, $manifest, json_decode($attempt->specification, true, flags: JSON_THROW_ON_ERROR));
    }

    /** @param list<array{id:string,name:string,format:string,size:int,digest:string}> $manifest
     * @param  array{material:string,color:string,quantity:string,notes:?string}  $specification
     */
    private function confirm(int $actor, int $customer, string $attemptId, array $manifest, array $specification): string
    {
        return DB::transaction(function () use ($actor, $customer, $attemptId, $manifest, $specification): string {
            $this->authorize($actor, $customer);
            $attempt = DB::table('submission_attempts')->where('id', $attemptId)->lockForUpdate()->first();
            if ($attempt === null) {
                throw new RuntimeException('The durable submission attempt is unavailable.');
            }
            if ($attempt->request_id !== null) {
                return $attempt->request_id;
            }
            $guard = DB::table('submission_object_guards')->where('attempt_id', $attemptId)->lockForUpdate()->first();
            if ($guard === null || $guard->state !== 'OPEN') {
                throw new ObjectUnavailable('REJECTED');
            }
            $id = (string) Str::uuid();
            $submitted = now();
            DB::table('print_requests')->insert([
                'id' => $id, 'attempt_id' => $attemptId, 'customer_id' => $customer, 'submitted_by' => $actor,
                ...$specification, 'status' => 'SUBMITTED', 'submitted_at' => $submitted,
            ]);
            foreach ($manifest as $position => $file) {
                DB::table('design_file_versions')->insert([
                    'id' => $file['id'], 'request_id' => $id, 'attempt_id' => $attemptId,
                    'customer_id' => $customer, 'submitted_by' => $actor, 'position' => $position + 1,
                    'original_name' => $file['name'], 'format' => $file['format'], 'size_bytes' => $file['size'],
                    'digest' => $file['digest'], 'submitted_at' => $submitted,
                ]);
            }
            DB::table('submission_attempts')->where('id', $attemptId)->update(['request_id' => $id, 'confirmed_at' => $submitted]);

            return $id;
        });
    }

    private function authorize(int $actor, int $customer): void
    {
        // Coordinating on the real relationship also serializes a concurrent
        // revocation; never trust a previously loaded User or browser selection.
        $relationship = DB::table('customer_user')->where('user_id', $actor)->where('customer_id', $customer)->lockForUpdate()->first();
        abort_unless($relationship !== null && $relationship->revoked_at === null, 404);
    }
}
