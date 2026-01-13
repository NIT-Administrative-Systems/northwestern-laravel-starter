<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;
use Throwable;

use function Laravel\Prompts\spin;

/**
 * Rebuilds the database from scratch with fresh migrations and seeders.
 *
 * This command is intended for local development to quickly reset the
 * database to a known state. It clears caches, runs migrations, seeds
 * the database, and regenerates IDE helper files.
 */
class RebuildDatabaseCommand extends Command
{
    protected $signature = 'db:rebuild';

    protected $description = 'Rebuild the database and regenerate IDE helper files';

    /** @var list<array{passed: bool}> */
    protected array $results = [];

    public function handle(): int
    {
        if (App::isProduction()) {
            $this->components->error('This command cannot be run in production.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Rebuilding Database');

        $steps = [
            'Clearing cache' => $this->clearCache(...),
            'Clearing queue' => fn () => $this->callSilently('queue:clear', ['--force' => true]),
            'Clearing schedule cache' => fn () => $this->callSilently('schedule:clear-cache'),
            'Running migrations' => fn () => $this->callSilently('migrate:fresh', ['--force' => true]),
            'Seeding database' => fn () => $this->callSilently('db:seed', ['--force' => true]),
            'Seeding demo data' => fn () => $this->callSilently('db:seed', ['--class' => 'DemoSeeder', '--force' => true]),
            'Generating IDE helpers' => fn () => $this->callSilently('ide-helper:models', ['-N' => true]),
        ];

        foreach ($steps as $name => $callback) {
            if (! $this->runStep($name, $callback)) {
                $this->displaySummary();

                return self::FAILURE;
            }
        }

        $this->displaySummary();
        $this->displayPostBuildInfo();

        return $this->allPassed() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Clear the application cache, ignoring errors if the cache table doesn't exist.
     */
    protected function clearCache(): void
    {
        try {
            $this->callSilently('cache:clear');
        } catch (Throwable) {
            // Ignore - database cache table may not exist yet
        }
    }

    /**
     * Run a step with a spinner, display the result immediately, and track it.
     *
     * @param  callable(): mixed  $callback
     */
    protected function runStep(string $name, callable $callback): bool
    {
        $error = null;

        $passed = spin(
            callback: function () use ($callback, &$error): bool {
                try {
                    $callback();

                    return true;
                } catch (Throwable $e) {
                    $error = $e->getMessage();

                    return false;
                }
            },
            message: "{$name}..."
        );

        if ($passed) {
            $this->line("  <fg=green>✓</> {$name}");
        } else {
            $this->line("  <fg=red>✗</> {$name}");
            if ($error) {
                $this->line("    <fg=red>{$error}</>");
            }
        }

        $this->results[] = ['passed' => $passed];

        return $passed;
    }

    protected function displaySummary(): void
    {
        $this->newLine();
        $this->line('  <fg=gray>─────────────────────────────────────────────────</>');
        $this->newLine();

        if ($this->allPassed()) {
            $this->line('  <fg=green>✓</> Database rebuild complete');
        } else {
            $passed = collect($this->results)->where('passed', true)->count();
            $failed = collect($this->results)->where('passed', false)->count();
            $this->line("  <fg=green>{$passed} passed</>, <fg=red>{$failed} failed</>");
        }

        $this->newLine();
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

        if (blank(config('auth.api.demo_user_token'))) {
            $this->components->warn("The demo API user's access token is missing.");
            $this->line('  <fg=gray>→</> A random value has been generated for <comment>api-nuit</comment>');
            $this->line('  <fg=gray>→</> For predictable local testing, add to your <comment>.env</comment> file:');
            $this->newLine();
            $this->line('    <fg=magenta>API_DEMO_USER_ACCESS_TOKEN=<fg=white>your-value-here</>');
            $this->newLine();
        }
    }

    protected function allPassed(): bool
    {
        return collect($this->results)->every(fn (array $result): bool => $result['passed']);
    }
}
