<?php

declare(strict_types=1);

namespace App\Mcp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;
use Laravel\Mcp\Server\Registrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel MCP's `WWW-Authenticate` challenge on the MCP server's 401, plus the scope a client
 * should request (`scope="mcp:use"`), as the MCP specification asks. Bound in place of Laravel
 * MCP's middleware in {@see \App\Mcp\McpServiceProvider}. Laravel MCP runs it globally
 * and on the route; each run replaces the header before adding the scope, so it appears once.
 */
class AddScopeToChallenge extends AddWwwAuthenticateHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);
        $challenge = $response->headers->get('WWW-Authenticate');

        if ($response->getStatusCode() === Response::HTTP_UNAUTHORIZED
            && is_string($challenge)
            && str_starts_with($challenge, 'Bearer realm="mcp", resource_metadata=')) {
            $response->headers->set('WWW-Authenticate', $challenge . ', scope="' . Registrar::OAUTH_SCOPE . '"');
        }

        return $response;
    }
}
