<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Console\Commands\RestoreLocalEnvironmentFilesCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RestoreLocalEnvironmentFilesCommand::class)]
class RestoreLocalEnvironmentFilesCommandTest extends TestCase
{
    public function test_fails_if_backup_file_does_not_exist(): void
    {
        $envBackupPath = base_path('.env.backup');

        File::shouldReceive('missing')
            ->with($envBackupPath)
            ->andReturn(true);

        /** @var PendingCommand $output */
        $output = $this->artisan('restore-env-files');

        $output
            ->expectsOutputToContain('The .env.backup file does not exist.')
            ->assertExitCode(1);
    }

    public function test_successfully_restores_environment_files(): void
    {
        $envPath = base_path('.env');
        $envCypressPath = base_path('.env.cypress');
        $envBackupPath = base_path('.env.backup');

        File::shouldReceive('missing')
            ->with($envBackupPath)
            ->andReturn(false);

        File::shouldReceive('exists')
            ->with($envPath)
            ->andReturn(true);

        File::shouldReceive('move')
            ->once()
            ->with($envPath, $envCypressPath)
            ->andReturn(true);

        File::shouldReceive('move')
            ->once()
            ->with($envBackupPath, $envPath)
            ->andReturn(true);

        /** @var PendingCommand $output */
        $output = $this->artisan('restore-env-files');

        $output
            ->expectsOutputToContain('Environment files have been restored successfully.')
            ->assertExitCode(0);
    }
}
