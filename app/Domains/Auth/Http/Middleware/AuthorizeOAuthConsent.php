<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Middleware;

use App\Domains\Api\CredentialAccess;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Auth\Models\OAuthClient;
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
 * Whether the signed-in person may connect this client, on Passport's consent screen and its
 * approve and deny endpoints, as {@see CredentialAccess} decides for issuing the client's kind:
 * refused while impersonating, for an MCP client without the Use MCP permission, for a
 * deactivated account, and with a 404 while the client's feature is off. Applied to Passport's
 * routes; a guest is left to Passport, which sends them to sign in.
 *
 * The client is the one Passport acts on: the screen's `client_id` query parameter, and on
 * approve and deny the authorization request Passport kept in the session, never a `client_id`
 * sent with the form. When there's no client to decide on, impersonating is still refused.
 */
class AuthorizeOAuthConsent
{
    public function __construct(
        private readonly CredentialAccess $credentials,
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

        $client = $this->client($request);

        if ($client instanceof OAuthClient) {
            $this->credentials->decide($user, CredentialOperation::Issue, CredentialKind::of($client), $user)->authorize();
        } elseif (resolve('impersonate')->isImpersonating()) {
            throw new AuthorizationException("Applications can't be connected while impersonating someone.");
        }

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
