<?php

declare(strict_types=1);

namespace App\Domains\Core\Services\ConfigValidation;

use App\Domains\Core\Attributes\StarterValidator;
use App\Domains\Core\Contracts\ConfigValidator;
use Illuminate\Support\Collection;

/**
 * Validates SSO credentials based on the active authentication strategy.
 *
 * The starter supports two SSO providers: Microsoft Entra ID (OAuth2) and
 * Online Passport (agentless WebSSO via ForgeRock). This validator detects
 * which provider is active and checks the appropriate credentials.
 */
#[StarterValidator(description: 'SSO Authentication')]
class SSOValidator implements ConfigValidator
{
    /** @var Collection<int, string> */
    protected Collection $missingVariables;

    protected bool $isOnlinePassport = false;

    public function shouldRun(): bool
    {
        return true;
    }

    public function validate(): bool
    {
        $this->isOnlinePassport = $this->detectOnlinePassport();

        $variables = $this->isOnlinePassport
            ? $this->onlinePassportVariables()
            : $this->entraIdVariables();

        $this->missingVariables = $variables
            ->filter(fn ($value): bool => blank($value))
            ->keys();

        return $this->missingVariables->isEmpty();
    }

    public function successMessage(): string
    {
        $provider = $this->isOnlinePassport ? 'Online Passport' : 'Entra ID';

        return "SSO configured for <comment>{$provider}</comment>";
    }

    public function errorMessage(): string
    {
        $provider = $this->isOnlinePassport ? 'Online Passport' : 'Entra ID';
        $count = $this->missingVariables->count();

        return "{$count} required {$provider} " . ($count === 1 ? 'variable is' : 'variables are') . ' not set';
    }

    /** @return list<string> */
    public function hints(): array
    {
        $hints = array_values($this->missingVariables
            ->map(fn (string $variable): string => "Set <comment>{$variable}</comment> in your .env file")
            ->all());

        $hints[] = 'See the <comment>WebSSO / Entra ID</comment> documentation for configuration details';

        return $hints;
    }

    protected function detectOnlinePassport(): bool
    {
        return filled(config('nusoa.sso.apigeeApiKey'))
            || config('nusoa.sso.strategy') === 'forgerock-direct';
    }

    /** @return Collection<string, mixed> */
    protected function entraIdVariables(): Collection
    {
        return collect([
            'AZURE_CLIENT_ID' => config('services.northwestern-azure.client_id'),
            'AZURE_CLIENT_SECRET' => config('services.northwestern-azure.client_secret'),
        ]);
    }

    /** @return Collection<string, mixed> */
    protected function onlinePassportVariables(): Collection
    {
        return collect([
            'WEBSSO_API_KEY' => config('nusoa.sso.apigeeApiKey'),
            'WEBSSO_API_URL_BASE' => config('nusoa.sso.apigeeBaseUrl'),
        ]);
    }
}
