<?php

namespace App\Access\Actions;

use App\Access\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;
use SensitiveParameterValue;
use Throwable;

final class StaffAccess
{
    public function bootstrap(int $targetId, string $source, string $reason): int
    {
        $this->text($source, 'source');
        $this->text($reason, 'reason');

        return DB::transaction(function () use ($targetId, $source, $reason): int {
            $control = $this->lock();
            User::findOrFail($targetId);
            if ($control->initial_owner_user_id !== null || DB::table('staff_responsibility_grants')->where('responsibility', 'owner')->exists()) {
                $this->deny('Initial Owner provisioning was already used. Use the approved recovery procedure for lost access.');
            }
            $id = $this->insertGrant($targetId, 'owner', null, $source, $reason);
            DB::table('access_control')->where('id', 1)->update(['initial_owner_user_id' => $targetId]);

            return $id;
        });
    }

    public function grant(int $actorId, int $targetId, string $responsibility, string $reason): int
    {
        $this->responsibility($responsibility);
        $this->text($reason, 'reason');

        return DB::transaction(function () use ($actorId, $targetId, $responsibility, $reason): int {
            $this->lock();
            $this->owner($actorId);
            User::findOrFail($targetId);
            if (DB::table('staff_responsibility_grants')->where('user_id', $targetId)->where('responsibility', $responsibility)->whereNull('revoked_at')->exists()) {
                $this->deny('This responsibility is already active.');
            }

            return $this->insertGrant($targetId, $responsibility, $actorId, null, $reason);
        });
    }

    public function revoke(int $actorId, int $grantId, string $reason): void
    {
        $this->text($reason, 'reason');
        DB::transaction(function () use ($actorId, $grantId, $reason): void {
            $this->lock();
            $this->owner($actorId);
            $grant = DB::table('staff_responsibility_grants')->where('id', $grantId)->whereNull('revoked_at')->first();
            if ($grant === null) {
                $this->deny('This responsibility is no longer active.');
            }
            if ($grant->responsibility === 'owner' && DB::table('staff_responsibility_grants')->where('responsibility', 'owner')->whereNull('revoked_at')->count() <= 1) {
                $this->deny('The last active Owner cannot be revoked.');
            }
            DB::table('staff_responsibility_grants')->where('id', $grantId)->update(['revoked_by' => $actorId, 'revoked_at' => now(), 'revoke_reason' => $reason]);
        });
    }

    /**
     * External verification references are attestations by a trusted executor,
     * not software proof of identity. No verification documents or secrets.
     *
     * @param  array{procedure: string, source: string, account: string, privileged?: string, infrastructure?: string, business?: string, reason: string}  $evidence
     */
    public function recover(?int $actorId, #[SensitiveParameter] ?string $actorPassword, int $targetId, #[SensitiveParameter] string $password, array $evidence): void
    {
        $verifiedActorId = null;
        try {
            foreach (['source', 'account', 'reason'] as $key) {
                $this->text($evidence[$key], $key);
            }
            if (! in_array($evidence['procedure'], ['ordinary-account-v1', 'privileged-account-v1', 'sole-owner-offline-v1'], true)) {
                $this->deny('Unknown verification procedure.');
            }
            if (mb_strlen($password) < 8 || strlen($password) > 72) {
                $this->deny('The new password must contain at least 8 characters and no more than 72 bytes.');
            }
            $protectedActorPassword = new SensitiveParameterValue($actorPassword);
            $protectedHash = new SensitiveParameterValue(Hash::make($password));
            DB::transaction(function () use ($actorId, $protectedActorPassword, $targetId, $protectedHash, $evidence, &$verifiedActorId): void {
                $this->lock();
                $target = User::query()->lockForUpdate()->findOrFail($targetId);
                if ($evidence['procedure'] === 'sole-owner-offline-v1') {
                    if ($actorId !== null || ! $target->hasResponsibility('owner') || DB::table('staff_responsibility_grants')->where('responsibility', 'owner')->whereNull('revoked_at')->count() !== 1) {
                        $this->deny('Offline break-glass is limited to the same sole active Owner.');
                    }
                    $this->text($evidence['infrastructure'] ?? '', 'infrastructure');
                    $this->text($evidence['business'] ?? '', 'business');
                } else {
                    if ($actorId === null) {
                        $this->deny('An authenticated current Owner is required.');
                    }
                    $this->owner($actorId);
                    $actor = User::findOrFail($actorId);
                    $actorPasswordValue = $protectedActorPassword->getValue();
                    if ($actorPasswordValue === null || ! Hash::check($actorPasswordValue, $actor->password)) {
                        $this->deny('Administrative credentials could not be authenticated.');
                    }
                    $verifiedActorId = $actorId;
                }
                $historicalStaff = DB::table('staff_responsibility_grants')->where('user_id', $targetId)->exists();
                if ($evidence['procedure'] === 'ordinary-account-v1' && $historicalStaff) {
                    $this->deny('Active or historical staff accounts require the separately trusted privileged procedure.');
                }
                if ($evidence['procedure'] !== 'ordinary-account-v1') {
                    $this->text($evidence['privileged'] ?? '', 'privileged');
                    if ($evidence['privileged'] === $evidence['account']) {
                        $this->deny('Privileged verification must reference the separately trusted procedure.');
                    }
                }
                $target->password = $protectedHash->getValue();
                $target->save();
                DB::table('sessions')->where('user_id', $targetId)->delete();
                $this->recordRecovery($actorId, $targetId, $evidence, 'completed');
            });
        } catch (Throwable $exception) {
            // A failed outcome never rolls forward a partial credential reset.
            // If evidence persistence itself fails, the command remains failed.
            if (User::query()->whereKey($targetId)->exists() && in_array($evidence['procedure'], ['ordinary-account-v1', 'privileged-account-v1', 'sole-owner-offline-v1'], true)) {
                DB::transaction(function () use ($verifiedActorId, $targetId, $evidence, $exception): void {
                    $this->lock();
                    $this->recordRecovery($verifiedActorId, $targetId, $evidence, $exception instanceof ValidationException ? 'denied' : 'failed');
                });
            }
            throw $exception;
        }
    }

    /** @param array{procedure: string, source: string, account: string, privileged?: string, infrastructure?: string, business?: string, reason: string} $evidence */
    public function deniedRecovery(int $targetId, array $evidence): void
    {
        // Early CLI denials have no authenticated acting Administrator.
        if (User::query()->whereKey($targetId)->exists()) {
            DB::transaction(function () use ($targetId, $evidence): void {
                $this->lock();
                $this->recordRecovery(null, $targetId, $evidence, 'denied');
            });
        }
    }

    private function lock(): object
    {
        return DB::table('access_control')->where('id', 1)->lockForUpdate()->firstOrFail();
    }

    private function owner(int $actorId): void
    {
        if (! User::findOrFail($actorId)->hasResponsibility('owner')) {
            $this->deny('Current Owner responsibility is required.');
        }
    }

    private function insertGrant(int $targetId, string $responsibility, ?int $actorId, ?string $source, string $reason): int
    {
        return DB::table('staff_responsibility_grants')->insertGetId(['user_id' => $targetId, 'responsibility' => $responsibility, 'granted_by' => $actorId, 'authorization_source' => $source, 'grant_reason' => $reason, 'granted_at' => now()]);
    }

    /** @param array{procedure: string, source: string, account: string, privileged?: string, infrastructure?: string, business?: string, reason: string} $evidence */
    private function recordRecovery(?int $actorId, int $targetId, array $evidence, string $outcome): void
    {
        foreach ($evidence as $key => $value) {
            $evidence[$key] = mb_substr($value, 0, 500);
        }
        DB::table('credential_recovery_history')->insert([
            'user_id' => $targetId, 'acting_user_id' => $actorId, 'procedure' => $evidence['procedure'], 'authorization_source' => $evidence['source'],
            'account_verification' => $evidence['account'], 'privileged_verification' => $evidence['privileged'] ?? null,
            'infrastructure_authorization' => $evidence['infrastructure'] ?? null, 'business_authorization' => $evidence['business'] ?? null,
            'customer_references' => json_encode(DB::table('customer_user')->where('user_id', $targetId)->pluck('customer_id')->all(), JSON_THROW_ON_ERROR),
            'reason' => $evidence['reason'], 'outcome' => $outcome, 'occurred_at' => now(),
        ]);
    }

    private function responsibility(string $responsibility): void
    {
        if (! in_array($responsibility, ['owner', 'operator'], true)) {
            $this->deny('Unknown staff responsibility.');
        }
    }

    private function text(string $value, string $field): void
    {
        if (trim($value) === '' || mb_strlen($value) > 500) {
            throw ValidationException::withMessages([$field => 'A non-secret reference or reason of at most 500 characters is required.']);
        }
    }

    private function deny(string $message): never
    {
        throw ValidationException::withMessages(['access' => $message]);
    }
}
