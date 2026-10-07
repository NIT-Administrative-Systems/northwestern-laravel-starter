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
use Symfony\Component\HttpFoundation\Response;

/**
 * Whether the signed-in person may connect this client, on Passport's consent screen and its
 * approve and deny endpoints, as {@see CredentialAccess} decides for issuing the client's kind:
 * refused while impersonating, for an MCP client without the Use MCP permission, for a
 * deactivated account, and with a 404 while the client's feature is off. Applied to Passport's
 * routes; a guest is left to Passport, which sends them to sign in.
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

        $clientId = $request->string('client_id')->toString();
        // Passport answers an unknown or malformed client ID itself.
        $client = Str::isUuid($clientId) ? OAuthClient::query()->find($clientId) : null;

        if ($client instanceof OAuthClient) {
            $this->credentials->decide($user, CredentialOperation::Issue, CredentialKind::of($client), $user)->authorize();
        }

        return $next($request);
    }
}
