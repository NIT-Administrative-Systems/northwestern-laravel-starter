<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Api\ApiScopes;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\Auth\Models\OAuthToken;
use App\Domains\Auth\Passport\GrantableScopeRepository;
use App\Domains\Auth\Passport\OAuthConsent;
use App\Domains\User\Models\User;
use App\Providers\Filament\AppPanelProvider;
use Carbon\CarbonInterval;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\AccessTokenRepository;
use Laravel\Passport\Bridge\ScopeRepository;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\Scope;
use Northwestern\SysDev\Chassis\Passport\ExpiringAccessTokenRepository;
use Northwestern\SysDev\Chassis\Passport\OAuthClientRepository;
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

        // Client IDs are UUIDs; a malformed one is an unknown client, not a database error.
        $this->app->bind(ClientRepository::class, OAuthClientRepository::class);

        // A person can't give an application a scope they don't hold themselves.
        $this->app->bind(ScopeRepository::class, GrantableScopeRepository::class);
    }

    public function boot(): void
    {
        Passport::useClientModel(OAuthClient::class);
        Passport::useTokenModel(OAuthToken::class);

        Passport::tokensCan(ApiScopes::all());

        // The consent screen is a public page, which renders in the app panel's context for its theme
        // and colors. Passport's routes don't carry the panel middleware, so the view sets it up.
        Passport::authorizationView(function (array $parameters): Response {
            Filament::setCurrentPanel(Filament::getPanel(AppPanelProvider::ID));
            Filament::bootCurrentPanel();

            /** @var array{client: OAuthClient, user: User, scopes: list<Scope>, request: Request, authToken: string} $parameters */
            return response()->view('public.oauth.authorize', resolve(OAuthConsent::class)->screen($parameters));
        });

        Passport::tokensExpireIn(CarbonInterval::hour());
        Passport::refreshTokensExpireIn(CarbonInterval::days(30));
        // The longest a personal access token may last; each token chooses a shorter life.
        Passport::personalAccessTokensExpireIn(CarbonInterval::days(365));
    }
}
