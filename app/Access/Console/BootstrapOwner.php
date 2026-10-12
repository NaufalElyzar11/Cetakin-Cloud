<?php

namespace App\Access\Console;

use App\Access\Actions\StaffAccess;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

final class BootstrapOwner extends Command
{
    protected $signature = 'access:bootstrap-owner {user : Existing User ID} {--source= : Non-secret trusted offline authorization source} {--reason= : Reason for initial provisioning}';

    protected $description = 'One-time trusted offline provisioning of the initial Owner; never a recovery command';

    public function handle(StaffAccess $access): int
    {
        if (! ctype_digit((string) $this->argument('user'))) {
            $this->error('An existing User ID is required.');

            return self::FAILURE;
        }
        try {
            $access->bootstrap((int) $this->argument('user'), (string) $this->option('source'), (string) $this->option('reason'));
        } catch (ValidationException $exception) {
            $this->error(implode(' ', $exception->validator->errors()->all()));

            return self::FAILURE;
        }
        $this->info('Initial Owner provisioned with retained offline authorization provenance.');

        return self::SUCCESS;
    }
}
