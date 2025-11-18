<?php

declare(strict_types=1);

namespace App\Domains\Core\Services\ConfigValidation;

use App\Domains\Core\Contracts\ConfigValidator;
use Illuminate\Support\Collection;

class EnvironmentVariablesValidator implements ConfigValidator
{
    /** @var Collection<int, string> */
    protected Collection $missingVariables;

    public function validate(): bool
    {
        $variables = collect([
            'AZURE_CLIENT_SECRET' => config('services.northwestern-azure.client_secret'),
            'DIRECTORY_SEARCH_API_KEY' => config('nusoa.directorySearch.apiKey'),
        ]);

        $this->missingVariables = $variables->filter(fn ($value): bool => blank($value))->keys();

        return $this->missingVariables->isEmpty();
    }

    public function successMessage(): string
    {
        return 'Required environment variables set.';
    }

    public function errorMessage(): string
    {
        $formattedMissingVariables = $this->missingVariables
            ->map(fn ($variable): string => "\t• {$variable}")
            ->implode("\n");

        return "The following environment variables are not set:\n" . $formattedMissingVariables;
    }
}
