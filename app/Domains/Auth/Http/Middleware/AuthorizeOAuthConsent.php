<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Middleware;

use App\Domains\Auth\Models\OAuthClient;
use App\Domains\Auth\Passport\OAuthConsent;
use App\Domains\User\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Passport\Bridge\Client;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Bridge\User as BridgeUser;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asks {@see OAuthConsent::authorize()} whether the signed-in person may connect the client, on
 * Passport's consent screen and its approve and deny endpoints: refused while impersonating, for
 * an MCP client without the Use MCP permission, for a deactivated account, and with a 404 while
 * the client's feature is off. Applied to Passport's routes; a guest is left to Passport, which
 * sends them to sign in.
 *
 * The client is the one Passport acts on: the screen's `client_id` query parameter, and on
 * approve and deny the authorization request Passport kept in the session, never a `client_id`
 * sent with the form.
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

        $this->consent->authorize($user, $this->client($request));

        return $next($request);
    }

    /**
     * Passport answers an unknown or malformed client ID itself.
     */
    private function client(Request $request): ?OAuthClient
    {
        $clientId = $request->routeIs('passport.authorizations.authorize')
            ? $request->query('client_id')
            : $this->sessionClientId($request);

        return is_string($clientId) && Str::isUuid($clientId) ? OAuthClient::query()->find($clientId) : null;
    }

    /**
     * The client of the authorization request Passport stored when it showed the consent
     * screen, unserialized as strictly as Passport does.
     */
    private function sessionClientId(Request $request): ?string
    {
        $stored = $request->session()->get('authRequest');

        if (! is_string($stored)) {
            return null;
        }

        $authRequest = unserialize($stored, ['allowed_classes' => [AuthorizationRequest::class, Client::class, Scope::class, BridgeUser::class]]);

        return $authRequest instanceof AuthorizationRequest ? $authRequest->getClient()->getIdentifier() : null;
    }
}
