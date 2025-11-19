<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Attempts to wake a potentially inactive serverless RDS database by establishing a connection.
 *
 * Useful to run as a deployment hook to avoid first-request timeouts.
 */
class WakeDatabaseCommand extends Command
{
    protected $signature = 'db:wake
                            {--max-attempts=5 : Maximum number of connection attempts (1-20)}
                            {--delay=5        : Seconds to wait between attempts (1-30)}';

    protected $description = 'Wakes up a potentially-inactive serverless RDS database by establishing a connection';

    public function handle(): int
    {
        $maxAttempts = $this->getValidatedMaxAttempts();
        $delaySeconds = $this->getValidatedDelay();

        $this->components->info('Waking database connection');
        $this->components->info("Configuration: {$maxAttempts} attempts, {$delaySeconds}s delay");

        retry(
            $maxAttempts,
            static fn () => DB::select('SELECT 1'),
            $delaySeconds * 1000
        );

        $this->components->success('Database connection established successfully');

        return self::SUCCESS;
    }

    private function getValidatedMaxAttempts(): int
    {
        $maxAttempts = (int) $this->option('max-attempts');

        if ($maxAttempts < 1 || $maxAttempts > 20) {
            $this->components->warn('Max attempts must be between 1 and 20. Using default value of 5.');

            return 5;
        }

        return $maxAttempts;
    }

    private function getValidatedDelay(): int
    {
        $delay = (int) $this->option('delay');

        if ($delay < 1 || $delay > 30) {
            $this->components->warn('Delay must be between 1 and 30 seconds. Using default value of 2.');

            return 2;
        }

        return $delay;
    }
}
