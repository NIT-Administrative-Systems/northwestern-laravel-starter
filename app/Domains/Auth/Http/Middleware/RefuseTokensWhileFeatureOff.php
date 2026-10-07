<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Middleware;

use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Auth\Models\OAuthClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses every grant at Passport's token endpoint for a client whose feature is off: a
 * service client or connected application while `api.enabled` is off, an MCP client while
 * `mcp.enabled` is off. A credential whose feature is off can't get or refresh a token; turning
 * the feature back on lets it continue, since nothing was revoked.
 *
 * The client comes from `client_id` in the body or the HTTP Basic username, as Passport reads
 * it. An unknown one is left to Passport. The refusal is the OAuth error for a client that may
 * not use the grant.
 */
class RefuseTokensWhileFeatureOff
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('passport.token')) {
            return $next($request);
        }

        // As League reads it: the body, then the HTTP Basic username. Never the query string, which
        // League ignores, so a client named there can't stand in for the one that gets the token.
        $clientId = (string) ($request->request->get('client_id') ?? $request->getUser() ?? '');
        $client = Str::isUuid($clientId) ? OAuthClient::query()->find($clientId) : null;

        if ($client instanceof OAuthClient && ! ($kind = CredentialKind::of($client))->isEnabled()) {
            return response()->json([
                'error' => 'unauthorized_client',
                'error_description' => ucfirst($kind->plural()) . ' are turned off.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return $next($request);
    }
}
