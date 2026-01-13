<?php

declare(strict_types=1);

namespace App\Domains\Core\Services\ConfigValidation;

use App\Domains\Core\Contracts\ConfigValidator;
use Illuminate\Support\Collection;

/**
 * Validates that required environment variables are set.
 *
 * This validator checks for critical environment variables that must be
 * configured for the application to function properly.
 */
class EnvironmentVariablesValidator implements ConfigValidator
{
    /** @var Collection<int, string> */
    protected Collection $missingVariables;

    public function name(): string
    {
        return 'Environment Variables';
    }

    public function validate(): bool
    {
        $variables = collect([
            'AZURE_CLIENT_SECRET' => config('services.northwestern-azure.client_secret'),
            'DIRECTORY_SEARCH_API_KEY' => config('nusoa.directorySearch.apiKey'),
        ]);

        $this->missingVariables = $variables
            ->filter(fn ($value): bool => blank($value))
            ->keys();

        return $this->missingVariables->isEmpty();
    }

    public function successMessage(): string
    {
        return 'All required environment variables are configured';
    }

    public function errorMessage(): string
    {
        $count = $this->missingVariables->count();

        return "{$count} required " . ($count === 1 ? 'variable is' : 'variables are') . ' not set';
    }

    public function hints(): array
    {
        return $this->missingVariables
            ->map(fn (string $variable): string => "Set <comment>{$variable}</comment> in your .env file")
            ->all();
    }
}
