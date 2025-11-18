<?php

declare(strict_types=1);

namespace App\Domains\Core\Services\ConfigValidation;

use App\Domains\Core\Contracts\ConfigValidator;
use Exception;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class QueueValidator implements ConfigValidator
{
    public function validate(): bool
    {
        $queueConnection = config('queue.default');

        // For Redis, check if the connection is successful
        if ($queueConnection === 'redis') {
            try {
                return Redis::connection()->client()->ping();
            } catch (Exception) {
                return false;
            }
        }

        // For other drivers, check if they're configured
        try {
            Queue::size();

            return true;
        } catch (Exception) {
            return false;
        }
    }

    public function successMessage(): string
    {
        $queueConnection = config('queue.default');

        return sprintf(
            'Queue connection successful (Using connection: <fg=yellow>%s</>).',
            strtoupper((string) $queueConnection)
        );
    }

    public function errorMessage(): string
    {
        $queueConnection = config('queue.default');

        if ($queueConnection === 'redis') {
            return 'Unable to establish a connection to Redis for queue processing. Ensure your Redis server is running and properly configured.';
        }

        return sprintf(
            'Queue connection failed for connection <fg=yellow>%s</>. Please check your configuration settings.',
            strtoupper((string) $queueConnection)
        );
    }
}
