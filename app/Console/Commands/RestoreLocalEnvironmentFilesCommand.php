<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Cypress occasionally fails to swap the `.env` files back after running the test suite. This command is useful
 * for local testing to easily rename the `.env` and `.env.backup` files back to their original filenames.
 */
class RestoreLocalEnvironmentFilesCommand extends Command
{
    protected $signature = 'restore-env-files';

    protected $description = 'Restore the local environment file from a backup created by Cypress.';

    public function handle(): int
    {
        $envPath = base_path('.env');
        $envCypressPath = base_path('.env.cypress');
        $envBackupPath = base_path('.env.backup');

        if (File::missing($envBackupPath)) {
            $this->components->error('The <options=bold;fg=green>.env.backup</> file does not exist.');

            return self::FAILURE;
        }

        if (File::exists($envPath)) {
            File::move($envPath, $envCypressPath);
        }
        File::move($envBackupPath, $envPath);

        $this->components->info('Environment files have been restored successfully.');

        return self::SUCCESS;
    }
}
