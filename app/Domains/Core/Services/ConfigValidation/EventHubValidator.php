<?php

declare(strict_types=1);

namespace App\Domains\Core\Services\ConfigValidation;

use Illuminate\Support\Collection;
use Northwestern\SysDev\Chassis\Attributes\ValidatesConfig;
use Northwestern\SysDev\Chassis\Contracts\ConfigValidator;

/**
 * Validates EventHub configuration when mock mode is disabled.
 *
 * When the application is configured to use the real EventHub API,
 * this validator ensures all required credentials are present.
 * When no EventHub credentials are configured at all, the validator
 * is skipped since EventHub is an optional integration.
 */
#[ValidatesConfig(description: 'EventHub')]
class EventHubValidator implements ConfigValidator
{
    /** @var Collection<int, string> */
    protected Collection $missingVariables;

    public function shouldRun(): bool
    {
        if (config('nusoa.eventHub.mock', true)) {
            return false;
        }

        $variables = collect([
            config('nusoa.eventHub.baseUrl'),
            config('nusoa.eventHub.apiKey'),
            config('nusoa.eventHub.hmacVerificationSharedSecret'),
        ]);

        return ! $variables->every(fn ($value): bool => blank($value));
    }

    public function validate(): bool
    {
        $variables = collect([
            'EVENT_HUB_BASE_URL' => config('nusoa.eventHub.baseUrl'),
            'EVENT_HUB_API_KEY' => config('nusoa.eventHub.apiKey'),
            'EVENT_HUB_HMAC_VERIFICATION_SHARED_SECRET' => config('nusoa.eventHub.hmacVerificationSharedSecret'),
        ]);

        $this->missingVariables = $variables
            ->filter(fn ($value): bool => blank($value))
            ->keys();

        return $this->missingVariables->isEmpty();
    }

    public function successMessage(): string
    {
        $baseUrl = config('nusoa.eventHub.baseUrl');

        return "EventHub configured for <comment>{$baseUrl}</comment>";
    }

    public function errorMessage(): string
    {
        $count = $this->missingVariables->count();

        return "{$count} required EventHub " . ($count === 1 ? 'variable is' : 'variables are') . ' not set (partial configuration detected)';
    }

    /** @return list<string> */
    public function hints(): array
    {
        $hints = array_values($this->missingVariables
            ->map(fn (string $variable): string => "Set <comment>{$variable}</comment> in your .env file")
            ->all());

        $hints[] = 'Or set <comment>EVENT_HUB_MOCK_ENABLED=true</comment> for local development';
        $hints[] = 'Or remove all EVENT_HUB_* variables if EventHub is not needed';

        return $hints;
    }
}
