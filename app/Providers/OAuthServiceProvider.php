<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\Auth\Models\OAuthToken;
use Carbon\CarbonInterval;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\AccessTokenRepository;
use Laravel\Passport\Passport;
use Northwestern\SysDev\Chassis\Passport\ExpiringAccessTokenRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel Passport, which issues the credentials for the application's API: client
 * credentials for service integrations, and personal access and authorization code
 * tokens for people.
 */
class OAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Passport registers its routes when it boots, so grants are switched off here.
        Passport::$deviceCodeGrantEnabled = false;

        // Personal access tokens get their own expiry, shorter than Passport's single lifetime.
        $this->app->bind(AccessTokenRepository::class, ExpiringAccessTokenRepository::class);
    }

    public function boot(): void
    {
        Passport::useClientModel(OAuthClient::class);
        Passport::useTokenModel(OAuthToken::class);

        Passport::tokensCan(self::scopes());

        Passport::authorizationView(fn (array $parameters): Response => response()->view('public.oauth.authorize', $parameters));

        Passport::tokensExpireIn(CarbonInterval::hour());
        Passport::refreshTokensExpireIn(CarbonInterval::days(30));
        // The longest a personal access token may last; each token chooses a shorter life.
        Passport::personalAccessTokensExpireIn(CarbonInterval::days(365));
    }

    /**
     * One scope per API-relevant permission, named after it: a token can do what its scopes
     * name, and no more than its user's permissions allow.
     *
     * @return array<string, string>
     */
    public static function scopes(): array
    {
        return collect(SystemPermission::cases())
            ->filter(fn (SystemPermission $permission): bool => $permission->isApiRelevant())
            ->mapWithKeys(fn (SystemPermission $permission): array => [$permission->value => $permission->description()])
            ->all();
    }
}
