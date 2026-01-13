<?php

declare(strict_types=1);

namespace App\Domains\Core\Services\ConfigValidation;

use App\Domains\Core\Contracts\ConfigValidator;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Validates the database connection is configured and accessible.
 */
class DatabaseValidator implements ConfigValidator
{
    protected ?string $errorReason = null;

    public function name(): string
    {
        return 'Database Connection';
    }

    public function validate(): bool
    {
        $connection = config('database.default');
        $databaseName = config("database.connections.{$connection}.database");

        if (blank($databaseName)) {
            $this->errorReason = 'not_configured';

            return false;
        }

        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');

            return true;
        } catch (Throwable) {
            $this->errorReason = 'connection_failed';

            return false;
        }
    }

    public function successMessage(): string
    {
        $driver = strtoupper((string) config('database.default'));
        $database = DB::connection()->getDatabaseName();

        return "Connected via <comment>{$driver}</comment> to <comment>{$database}</comment>";
    }

    public function errorMessage(): string
    {
        return match ($this->errorReason) {
            'not_configured' => 'Database name is not configured',
            default => 'Unable to establish database connection',
        };
    }

    public function hints(): array
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        return match ($this->errorReason) {
            'not_configured' => [
                'Set <comment>DB_DATABASE</comment> in your .env file',
            ],
            default => [
                "Verify the database <comment>{$database}</comment> exists",
                'Check <comment>DB_HOST</comment>, <comment>DB_PORT</comment>, <comment>DB_USERNAME</comment>, and <comment>DB_PASSWORD</comment> in your .env file',
                'Ensure the database server is running and accessible',
            ],
        };
    }
}
