<?php

namespace App\Access\Console;

use App\Access\Actions\StaffAccess;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

final class RecoverCredentials extends Command
{
    protected $signature = 'access:recover-credentials {user : Existing affected User ID}
        {--mode= : ordinary, privileged, or sole-owner}
        {--actor= : Current acting Owner ID for ordinary/privileged recovery}
        {--source= : Non-secret execution/authorization source}
        {--account-verification= : Completed account-level verification reference}
        {--privileged-verification= : Separately trusted privileged verification reference}
        {--infrastructure= : Sole-Owner trusted infrastructure authorization reference}
        {--business-authorization= : Sole-Owner business authorization reference}
        {--reason= : Reason for credential recovery}';

    protected $description = 'Trusted offline, externally verified same-User credential recovery; hidden password prompts only';

    public function handle(StaffAccess $access): int
    {
        // Never accept a password in process arguments or fall back to echo.
        if (! $this->input->isInteractive() || ! defined('STDIN') || ! stream_isatty(STDIN)) {
            $this->error('Recovery requires an interactive trusted terminal with hidden password input.');

            return self::FAILURE;
        }
        $procedures = ['ordinary' => 'ordinary-account-v1', 'privileged' => 'privileged-account-v1', 'sole-owner' => 'sole-owner-offline-v1'];
        $mode = (string) $this->option('mode');
        if (! isset($procedures[$mode]) || ! ctype_digit((string) $this->argument('user'))) {
            $this->error('An existing User ID and an explicit recovery mode are required.');

            return self::FAILURE;
        }
        $evidence = [
            'procedure' => $procedures[$mode], 'source' => (string) $this->option('source'),
            'account' => (string) $this->option('account-verification'), 'privileged' => (string) $this->option('privileged-verification'),
            'infrastructure' => (string) $this->option('infrastructure'), 'business' => (string) $this->option('business-authorization'),
            'reason' => (string) $this->option('reason'),
        ];
        $actorId = null;
        $actorPassword = null;
        if ($mode !== 'sole-owner') {
            if (! ctype_digit((string) $this->option('actor'))) {
                $access->deniedRecovery((int) $this->argument('user'), $evidence);
                $this->error('Current acting Owner ID is required.');

                return self::FAILURE;
            }
            $actorId = (int) $this->option('actor');
            $actorPassword = $this->secret('Acting Owner password', false);
        } elseif ($this->option('actor') !== null) {
            $access->deniedRecovery((int) $this->argument('user'), $evidence);
            $this->error('Offline break-glass records external authority, not a fabricated acting Administrator.');

            return self::FAILURE;
        }
        $this->warn('Proceed only after the documented external account verification and applicable separate authority checks have actually completed. References are attestations, not identity proof.');
        if (! $this->confirm('Have you completed and retained the required verification/authorization outside Git?', false)) {
            $access->deniedRecovery((int) $this->argument('user'), $evidence);
            $this->error('Recovery denied: verification/authorization was not confirmed.');

            return self::FAILURE;
        }
        $password = $this->secret('New password (at least 8 characters; no more than 72 bytes)', false);
        $confirmation = $this->secret('Confirm new password', false);
        if (! is_string($password) || $password !== $confirmation) {
            $access->deniedRecovery((int) $this->argument('user'), $evidence);
            $this->error('New password confirmation failed. Nothing was changed.');

            return self::FAILURE;
        }
        try {
            $access->recover($actorId, $actorPassword, (int) $this->argument('user'), $password, $evidence);
        } catch (ValidationException $exception) {
            $this->error(implode(' ', $exception->validator->errors()->all()));

            return self::FAILURE;
        } catch (Throwable) {
            // Database exception bindings may contain credential hashes. Do not
            // print/report those exceptions through the operational interface.
            $this->error('Recovery could not be confirmed. No success is claimed; inspect protected recovery evidence before retrying.');

            return self::FAILURE;
        } finally {
            unset($password, $confirmation, $actorPassword);
        }
        $this->info('Credentials reset for the same User. Previous sessions invalidated. No relationships or responsibilities changed.');

        return self::SUCCESS;
    }
}
