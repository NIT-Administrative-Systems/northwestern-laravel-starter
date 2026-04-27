<?php

declare(strict_types=1);

namespace App\Domains\Core\Services\ConfigValidation;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Northwestern\SysDev\Chassis\Attributes\ValidatesConfig;
use Northwestern\SysDev\Chassis\Contracts\ConfigValidator;
use Throwable;

/**
 * Validates the queue connection is configured and accessible.
 */
#[ValidatesConfig(description: 'Queue Connection')]
class QueueValidator implements ConfigValidator
{
    public function shouldRun(): bool
    {
        return true;
    }

    public function validate(): bool
    {
        $queueConnection = config('queue.default');

        if ($queueConnection === 'redis') {
            try {
                return Redis::connection()->client()->ping();
            } catch (Throwable) {
                return false;
            }
        }

        try {
            Queue::size();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function successMessage(): string
    {
        $driver = strtoupper((string) config('queue.default'));

        return "Queue driver <comment>{$driver}</comment> is operational";
    }

    public function errorMessage(): string
    {
        $driver = config('queue.default');

        return match ($driver) {
            'redis' => 'Unable to connect to Redis for queue processing',
            default => 'Queue connection failed',
        };
    }

    public function hints(): array
    {
        $driver = config('queue.default');

        return match ($driver) {
            'redis' => [
                'Ensure Redis is running: <comment>brew services start redis</comment> or <comment>docker-compose up -d redis</comment>',
                'Verify <comment>REDIS_HOST</comment> and <comment>REDIS_PORT</comment> in your .env file',
            ],
            'database' => [
                'Ensure the database is accessible and the jobs table exists',
                'Run <comment>php artisan queue:table && php artisan migrate</comment> if needed',
            ],
            default => [
                "Check your <comment>QUEUE_CONNECTION</comment> setting (currently: {$driver})",
            ],
        };
    }
}
