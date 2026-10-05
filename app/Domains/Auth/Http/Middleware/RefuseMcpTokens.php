<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Laravel\Mcp\Server\Registrar;
use Northwestern\SysDev\Chassis\ValueObjects\ApiRequestContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses MCP tokens on the REST API, after {@see AuthenticatePassportToken}. A token issued
 * to an MCP client carries `mcp:use` and works only on the MCP server ({@see RequireMcpAccess}),
 * so a client connected for its tools can't reach the API's routes with it.
 */
class RefuseMcpTokens
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $scopes = Context::get(ApiRequestContext::OAUTH_SCOPES);

        if (is_array($scopes) && in_array(Registrar::OAUTH_SCOPE, $scopes, true)) {
            throw new AuthorizationException('MCP tokens can only be used with the MCP server.');
        }

        return $next($request);
    }
}
