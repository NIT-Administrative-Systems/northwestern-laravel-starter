<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Queue;

class RebuildDatabaseCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'db:rebuild';

    protected $description = 'Rebuild the database and regenerate IDE helper files.';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            $this->components->warn('Database rebuild cancelled.');

            return self::FAILURE;
        }

        try {
            $this->call('cache:clear');
        } catch (Exception) {
            // The cache store is likely set to `database` - this command will fail if the database has not been migrated yet.
        }

        $this->call('queue:clear');
        $this->call('migrate:fresh', ['--seed' => true]);
        $this->call('db:seed', ['--class' => 'DemoSeeder']);
        $this->callSilent('ide-helper:models', ['-N' => true]);

        $this->components->success('Database rebuild complete.');

        $queueSize = Queue::size();

        if ($queueSize > 0) {
            $this->newLine();
            $this->components->warn("There are <options=bold;fg=green>{$queueSize}</> jobs pending in the queue.");
            $this->components->info('Ensure that your queue worker is running with: <options=bold;fg=green>php artisan queue:work</>');
        }

        if (blank(config('auth.api.demo_user_token'))) {
            $this->newLine(count: 2);
            $this->warn("The demo API user's (<options=bold;fg=magenta>api-nuit</>) default token is missing; a random value has been generated.");

            $this->newLine();
            $this->warn('For predictable local testing, you should add the following to your <options=underscore>.env</> file:');

            $this->newLine();
            $this->warn("\t<fg=magenta>API_DEMO_USER_TOKEN=<options=bold>your‑value‑here</></>");
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
