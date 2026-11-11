<?php

declare(strict_types=1);

namespace App\Domains\Auth\Enums;

/**
 * Northwestern single sign-on through laravel-soa: Online
 * Passport (agentless WebSSO) or Microsoft Entra ID. An
 * application uses one of them, {@see configured()}.
 */
enum SsoProvider: string
{
    case OnlinePassport = 'online-passport';
    case EntraId = 'entra-id';

    /**
     * Get the provider people sign in with, the only one
     * with routes: Online Passport when it's set up,
     * else Entra ID when that's set up, else none.
     */
    public static function configured(): ?self
    {
        foreach (self::cases() as $provider) {
            if ($provider->isConfigured()) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Determine whether this provider is set up. Online Passport needs its API key
     * or the ForgeRock direct strategy; Entra ID needs its client ID and secret.
     */
    public function isConfigured(): bool
    {
        return match ($this) {
            self::OnlinePassport => filled(config('nusoa.sso.apigeeApiKey')) || config('nusoa.sso.strategy') === 'forgerock-direct',
            self::EntraId => filled(config('services.northwestern-azure.client_id')) && filled(config('services.northwestern-azure.client_secret')),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::OnlinePassport => 'Online Passport',
            self::EntraId => 'Entra ID',
        };
    }

    public function loginRoute(): string
    {
        return match ($this) {
            self::OnlinePassport => 'login-websso',
            self::EntraId => 'login-oauth-redirect',
        };
    }

    public function logoutRoute(): string
    {
        return match ($this) {
            self::OnlinePassport => 'login-websso-logout',
            self::EntraId => 'login-oauth-logout',
        };
    }
}
