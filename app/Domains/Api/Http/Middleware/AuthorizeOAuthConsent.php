<?php

declare(strict_types=1);

namespace App\Domains\Api\Http\Middleware;

use App\Domains\Api\Models\OAuthClient;
use App\Domains\Api\Passport\OAuthConsent;
use App\Domains\User\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Passport\Bridge\Client;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Bridge\User as BridgeUser;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asks {@see OAuthConsent::authorize()} whether the signed-in person may connect
 * the client, on Passport's consent screen and its approve and deny endpoints.
 * It refuses while they're impersonating, for a deactivated account, and for
 * an MCP client if the person lacks Use MCP or the client doesn't ask for
 * `mcp:use`. It answers 404 while the client's feature is turned off.
 *
 * It applies to Passport's routes, and leaves a guest to Passport to sign in.
 *
 * The client and scopes are the ones Passport acts on: on the
 * screen, its `client_id` and `scope` query parameters, and
 * on approve and deny, the authorization request Passport
 * kept in the session, never values sent with the form.
 */
class AuthorizeOAuthConsent
{
    public function __construct(
        private readonly OAuthConsent $consent,
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if (! $request->routeIs('passport.authorizations.*') || ! $user instanceof User) {
            return $next($request);
        }

        if ($request->routeIs('passport.authorizations.authorize')) {
            $clientId = $request->query('client_id');
            $scope = $request->query('scope');
            $scopes = is_string($scope) ? array_values(array_filter(explode(' ', $scope))) : [];
        } else {
            $authRequest = $this->sessionAuthRequest($request);
            $clientId = $authRequest?->getClient()->getIdentifier();
            $scopes = array_map(fn (ScopeEntityInterface $scope): string => $scope->getIdentifier(), $authRequest?->getScopes() ?? []);
        }

        // Passport answers an unknown or malformed client ID itself.
        $client = is_string($clientId) && Str::isUuid($clientId) ? OAuthClient::query()->find($clientId) : null;

        $this->consent->authorize($user, $client, $scopes);

        return $next($request);
    }

    /**
     * Get the authorization request Passport stored when it showed the
     * consent screen, unserialized as strictly as Passport does it.
     */
    private function sessionAuthRequest(Request $request): ?AuthorizationRequest
    {
        $stored = $request->session()->get('authRequest');

        if (! is_string($stored)) {
            return null;
        }

        $authRequest = unserialize($stored, ['allowed_classes' => [AuthorizationRequest::class, Client::class, Scope::class, BridgeUser::class]]);

        return $authRequest instanceof AuthorizationRequest ? $authRequest : null;
    }
}
