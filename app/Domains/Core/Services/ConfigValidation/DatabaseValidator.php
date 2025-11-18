<?php

declare(strict_types=1);

namespace App\Domains\Core\Services\ConfigValidation;

use App\Domains\Core\Contracts\ConfigValidator;
use Exception;
use Illuminate\Support\Facades\DB;

class DatabaseValidator implements ConfigValidator
{
    public function validate(): bool
    {
        $connection = config('database.default');
        $databaseName = config("database.connections.{$connection}.database");

        if (blank($databaseName)) {
            return false;
        }

        try {
            $pdo = DB::connection()->getPdo();

            DB::select('SELECT 1');

            return true;
        } catch (Exception) {
            return false;
        }
    }

    public function successMessage(): string
    {
        $connection = config('database.default');
        $databaseName = DB::connection()->getDatabaseName();

        return sprintf(
            'Database connection successful (Using %s: <fg=yellow>%s</>).',
            strtoupper((string) $connection),
            $databaseName
        );
    }

    public function errorMessage(): string
    {
        $connection = config('database.default');
        $databaseName = config("database.connections.{$connection}.database");

        if (blank($databaseName)) {
            return 'Database name is not configured. Please set <fg=yellow>DB_DATABASE</> in your .env file.';
        }

        return sprintf(
            'Unable to establish a connection to the database <fg=yellow>%s</>. Ensure your database exists and configuration settings are correct.',
            $databaseName,
        );
    }
}
