<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Exceptions\MissingScopeException;
use Northwestern\SysDev\Chassis\Enums\ApiPrincipalType;
use Northwestern\SysDev\Chassis\ValueObjects\ApiRequestContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admits only MCP tokens to the MCP server, after {@see AuthenticatePassportToken}: a token a
 * person approved for an MCP client, carrying `mcp:use`. Service client, personal access and
 * application tokens are refused, as {@see RefuseMcpTokens} refuses MCP tokens on the REST
 * API. Whether the person may still use MCP was already decided when their token was
 * authenticated.
 */
class RequireMcpAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $scopes = Context::get(ApiRequestContext::OAUTH_SCOPES);

        if (Context::get(ApiRequestContext::PRINCIPAL_TYPE) !== ApiPrincipalType::User->value
            || ! is_array($scopes)
            || ! in_array(Registrar::OAUTH_SCOPE, $scopes, true)) {
            throw new MissingScopeException(Registrar::OAUTH_SCOPE);
        }

        return $next($request);
    }
}
