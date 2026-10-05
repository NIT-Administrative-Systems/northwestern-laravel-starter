<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\Sample\DemoUserSeeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Northwestern\SysDev\Chassis\Console\Commands\RebuildDatabaseCommand as BaseRebuildDatabaseCommand;

/**
 * Rebuilds the database from scratch with fresh migrations and seeders.
 *
 * This command is intended for local development to quickly reset the
 * database to a known state. It clears caches, runs migrations, seeds
 * the database, and regenerates IDE helper files.
 */
class RebuildDatabaseCommand extends BaseRebuildDatabaseCommand
{
    protected $signature = 'db:rebuild';

    protected $description = 'Rebuild the database and regenerate IDE helper files';

    /**
     * @return array<string, callable(): mixed>
     */
    protected function appendSteps(): array
    {
        return [
            // Passport signs access tokens with these; deployed environments set PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY.
            'Generating OAuth signing keys' => fn () => file_exists(Passport::keyPath('oauth-private.key')) || $this->callSilently('passport:keys') === self::SUCCESS,
            'Seeding demo data' => fn () => $this->callSilently('db:seed', ['--class' => 'DemoSeeder', '--force' => true]),
            'Generating IDE helpers' => fn () => $this->callSilently('ide-helper:models', ['-N' => true]),
        ];
    }

    protected function displayPostBuildInfo(): void
    {
        if (! $this->allPassed()) {
            return;
        }

        $queueSize = Queue::size();

        if ($queueSize > 0) {
            $this->components->warn("There are {$queueSize} jobs pending in the queue.");
            $this->line('  <fg=gray>→</> Run <comment>php artisan queue:work</comment> to process them');
            $this->newLine();
        }

        if (config('api.enabled') && App::isLocal()) {
            $this->components->info('The demo API user <comment>api-nuit</comment> has a service client for local testing:');
            $this->line('  <fg=gray>→</> Client ID: <comment>' . DemoUserSeeder::DEMO_CLIENT_ID . '</comment>');
            $this->line('  <fg=gray>→</> Client secret: <comment>' . DemoUserSeeder::DEMO_CLIENT_SECRET . '</comment>');
            $this->line('  <fg=gray>→</> Exchange them at <comment>POST /oauth/token</comment> with <comment>grant_type=client_credentials</comment>');
            $this->newLine();
        }
    }
}
