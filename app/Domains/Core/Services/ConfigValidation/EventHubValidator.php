<?php

declare(strict_types=1);

namespace App\Domains\Core\Services\ConfigValidation;

use App\Domains\Core\Contracts\ConfigValidator;
use Illuminate\Support\Collection;

/**
 * Validates EventHub configuration when mock mode is disabled.
 *
 * When the application is configured to use the real EventHub API,
 * this validator ensures all required credentials are present.
 * When no EventHub credentials are configured at all, the validator
 * reports as skipped since EventHub is an optional integration.
 */
class EventHubValidator implements ConfigValidator
{
    /** @var Collection<int, string> */
    protected Collection $missingVariables;

    protected bool $isMocked = false;

    protected bool $isSkipped = false;

    public function name(): string
    {
        return 'EventHub';
    }

    public function validate(): bool
    {
        $this->isMocked = (bool) config('nusoa.eventHub.mock', true);

        if ($this->isMocked) {
            return true;
        }

        $variables = collect([
            'EVENT_HUB_BASE_URL' => config('nusoa.eventHub.baseUrl'),
            'EVENT_HUB_API_KEY' => config('nusoa.eventHub.apiKey'),
            'EVENT_HUB_HMAC_VERIFICATION_SHARED_SECRET' => config('nusoa.eventHub.hmacVerificationSharedSecret'),
        ]);

        $allBlank = $variables->every(fn ($value): bool => blank($value));

        if ($allBlank) {
            $this->isSkipped = true;

            return true;
        }

        $this->missingVariables = $variables
            ->filter(fn ($value): bool => blank($value))
            ->keys();

        return $this->missingVariables->isEmpty();
    }

    public function successMessage(): string
    {
        if ($this->isMocked) {
            return 'EventHub is running in <comment>mock mode</comment>';
        }

        if ($this->isSkipped) {
            return 'EventHub is <comment>not configured</comment> (optional)';
        }

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
