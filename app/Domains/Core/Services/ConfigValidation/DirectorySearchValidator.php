<?php

declare(strict_types=1);

namespace App\Domains\Core\Services\ConfigValidation;

use Northwestern\SysDev\Chassis\Attributes\ValidatesConfig;
use Northwestern\SysDev\Chassis\Contracts\ConfigValidator;

/**
 * Validates that the Directory Search API key is configured.
 *
 * The Directory Search API is required for user provisioning during
 * SSO login and for stakeholder seeding.
 */
#[ValidatesConfig(description: 'Directory Search')]
class DirectorySearchValidator implements ConfigValidator
{
    public function shouldRun(): bool
    {
        return true;
    }

    public function validate(): bool
    {
        return filled(config('nusoa.directorySearch.apiKey'));
    }

    public function successMessage(): string
    {
        return 'Directory Search API key is configured';
    }

    public function errorMessage(): string
    {
        return 'Directory Search API key is not set';
    }

    /** @return list<string> */
    public function hints(): array
    {
        return [
            'Set <comment>DIRECTORY_SEARCH_API_KEY</comment> in your .env file',
            'Obtain an API key from the <comment>API Service Registry</comment>',
        ];
    }
}
