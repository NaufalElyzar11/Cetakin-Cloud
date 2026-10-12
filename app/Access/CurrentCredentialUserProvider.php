<?php

namespace App\Access;

use App\Access\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use LogicException;
use SensitiveParameter;

final class CurrentCredentialUserProvider extends EloquentUserProvider
{
    /**
     * Preserve Laravel's supported hash upgrades without allowing an old login
     * snapshot to overwrite a credential reset that committed after validation.
     */
    public function rehashPasswordIfRequired(Authenticatable $user, #[SensitiveParameter] array $credentials, bool $force = false): void
    {
        if (! $user instanceof User) {
            throw new LogicException('The Access provider requires the approved User model.');
        }
        $previousHash = $user->getAuthPassword();
        if (! $force && ! $this->hasher->needsRehash($previousHash)) {
            return;
        }
        $replacementHash = $this->hasher->make($credentials['password']);
        $updated = User::query()->whereKey($user->getAuthIdentifier())->where('password', $previousHash)->update(['password' => $replacementHash, 'updated_at' => now()]);
        if ($updated === 1) {
            $user->password = $replacementHash;
        }
        // If reset won, retain the stale snapshot's old hash. SessionGuard
        // stamps it and auth.session denies all subsequent protected access.
    }
}
