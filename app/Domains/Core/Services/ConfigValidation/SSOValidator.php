<?php

declare(strict_types=1);

namespace App\Domains\Core\Services\ConfigValidation;

use App\Domains\Auth\Enums\SsoProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Northwestern\SysDev\Chassis\Attributes\ValidatesConfig;
use Northwestern\SysDev\Chassis\Contracts\ConfigValidator;

/**
 * Validates SSO credentials based on the active authentication strategy.
 *
 * It checks the credentials of the provider in use: Microsoft Entra ID
 * (OAuth 2) or Online Passport (agentless WebSSO through ForgeRock).
 *
 * SSO is optional locally, where sign-in-as and email codes do instead,
 * so a local environment without any SSO variables skips the check.
 */
#[ValidatesConfig(description: 'SSO Authentication')]
class SSOValidator implements ConfigValidator
{
    /** @var Collection<int, string> */
    protected Collection $missingVariables;

    protected bool $isOnlinePassport = false;

    public function shouldRun(): bool
    {
        return ! (App::environment('local') && $this->entraIdVariables()->filter()->isEmpty() && ! $this->detectOnlinePassport());
    }

    public function validate(): bool
    {
        $this->isOnlinePassport = $this->detectOnlinePassport();

        $variables = $this->isOnlinePassport
            ? $this->onlinePassportVariables()
            : $this->entraIdVariables();

        $this->missingVariables = $variables
            ->filter(fn (string|int|float|bool|null $value): bool => blank($value))
            ->keys();

        return $this->missingVariables->isEmpty();
    }

    public function successMessage(): string
    {
        $provider = ($this->isOnlinePassport ? SsoProvider::OnlinePassport : SsoProvider::EntraId)->label();

        return "SSO configured for <comment>{$provider}</comment>";
    }

    public function errorMessage(): string
    {
        $provider = ($this->isOnlinePassport ? SsoProvider::OnlinePassport : SsoProvider::EntraId)->label();
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
        return SsoProvider::OnlinePassport->isConfigured();
    }

    /** @return Collection<string, string|null> */
    protected function entraIdVariables(): Collection
    {
        return collect([
            'AZURE_CLIENT_ID' => config('services.northwestern-azure.client_id'),
            'AZURE_CLIENT_SECRET' => config('services.northwestern-azure.client_secret'),
        ]);
    }

    /** @return Collection<string, string|null> */
    protected function onlinePassportVariables(): Collection
    {
        return collect([
            'WEBSSO_API_KEY' => config('nusoa.sso.apigeeApiKey'),
            'WEBSSO_API_URL_BASE' => config('nusoa.sso.apigeeBaseUrl'),
        ]);
    }
}
