<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Api\CredentialAccess;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Http\Middleware\AddMcpScopeToChallenge;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\Auth\Models\OAuthToken;
use App\Domains\Auth\Passport\GrantableScopeRepository;
use App\Domains\User\Models\User;
use App\Providers\Filament\AppPanelProvider;
use Carbon\CarbonInterval;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Bridge\AccessTokenRepository;
use Laravel\Passport\Bridge\ScopeRepository;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\Scope;
use Northwestern\SysDev\Chassis\Passport\ExpiringAccessTokenRepository;
use Northwestern\SysDev\Chassis\Passport\OAuthClientRepository;
use Northwestern\SysDev\Chassis\ValueObjects\OAuthRedirectTarget;
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

        // The MCP server's 401 challenge also names the scope a client should request.
        $this->app->bind(AddWwwAuthenticateHeader::class, AddMcpScopeToChallenge::class);

        // A person can't give an application a scope they don't hold themselves.
        $this->app->bind(ScopeRepository::class, GrantableScopeRepository::class);
    }

    public function boot(): void
    {
        Passport::useClientModel(OAuthClient::class);
        Passport::useTokenModel(OAuthToken::class);

        // MCP clients get only `mcp:use`, which no REST route accepts, so their tokens and REST tokens
        // can't stand in for each other. It isn't offered for personal tokens or applications.
        Passport::tokensCan([...self::scopes(), Registrar::OAUTH_SCOPE => "Use this application's tools from an AI client"]);

        // The consent screen is a public page, which renders in the app panel's context for its theme
        // and colors. Passport's routes don't carry the panel middleware, so the view sets it up.
        Passport::authorizationView(function (array $parameters): Response {
            Filament::setCurrentPanel(Filament::getPanel(AppPanelProvider::ID));
            Filament::bootCurrentPanel();

            /** @var Request $request */
            $request = $parameters['request'];
            /** @var OAuthClient $client */
            $client = $parameters['client'];

            // Where approving sends the person. Passport has already matched the redirect URI to one the
            // client registered; without one, it uses the client's only registered URI.
            $parameters['redirectTarget'] = OAuthRedirectTarget::from($request->string('redirect_uri')->toString() ?: $client->redirect_uris[0]);

            // Only the scopes the person may grant, the same ones GrantableScopeRepository lets the token carry.
            /** @var User $user */
            $user = $parameters['user'];
            $grantable = resolve(CredentialAccess::class)->grantableScopes($user, CredentialKind::of($client));
            $parameters['scopes'] = array_values(array_filter($parameters['scopes'], fn (Scope $scope): bool => array_key_exists($scope->id, $grantable)));

            return response()->view('public.oauth.authorize', $parameters);
        });

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

    /**
     * A scope's name in the interface: the permission it's named after ("View Users"), or "Use
     * Tools" for the MCP scope.
     */
    public static function scopeLabel(string $scope): string
    {
        return $scope === Registrar::OAUTH_SCOPE ? 'Use Tools' : (SystemPermission::tryFrom($scope)?->getLabel() ?? $scope);
    }
}
