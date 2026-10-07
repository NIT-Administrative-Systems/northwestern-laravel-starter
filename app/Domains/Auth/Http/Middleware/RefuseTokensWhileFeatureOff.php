<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses the client-credentials grant at Passport's token endpoint while `api.enabled` is
 * off, so service clients can't get tokens for an API that isn't there. The other grants
 * stay: MCP clients use the authorization-code and refresh grants at the same endpoint.
 *
 * The refusal is an OAuth error response, as Passport would give for a grant it doesn't support.
 */
class RefuseClientCredentialsWhileApiDisabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('api.enabled') && $request->routeIs('passport.token') && $request->input('grant_type') === 'client_credentials') {
            return response()->json([
                'error' => 'unsupported_grant_type',
                'error_description' => 'The client credentials grant is not available while the API is turned off.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return $next($request);
    }
}
