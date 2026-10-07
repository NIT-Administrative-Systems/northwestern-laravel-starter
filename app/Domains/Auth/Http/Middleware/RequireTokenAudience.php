<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Middleware;

use App\Domains\Auth\Enums\TokenAudience;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Exceptions\MissingScopeException;
use Northwestern\SysDev\Chassis\Enums\ApiPrincipalType;
use Northwestern\SysDev\Chassis\ValueObjects\ApiRequestContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps each token on its side of the application, after {@see AuthenticatePassportToken}. A
 * token issued to an MCP client carries `mcp:use` and works only on the MCP server, so a client
 * connected for its tools can't reach the REST API with it; and the MCP server admits only
 * those tokens, so service client, personal access and application tokens can't call tools.
 * Whether the person may still use MCP was already decided when their token was authenticated.
 *
 * ```php
 * Route::middleware([AuthenticatePassportToken::class, RequireTokenAudience::for(TokenAudience::Api)]);
 * ```
 */
class RequireTokenAudience
{
    public static function for(TokenAudience $audience): string
    {
        return static::class . ':' . $audience->value;
    }

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws AuthorizationException|MissingScopeException
     */
    public function handle(Request $request, Closure $next, string $audience): Response
    {
        $scopes = Context::get(ApiRequestContext::OAUTH_SCOPES);
        $isMcpToken = is_array($scopes) && in_array(Registrar::OAUTH_SCOPE, $scopes, true);

        match (TokenAudience::from($audience)) {
            TokenAudience::Api => $isMcpToken
                ? throw new AuthorizationException('MCP tokens can only be used with the MCP server.')
                : null,
            TokenAudience::Mcp => $isMcpToken && Context::get(ApiRequestContext::PRINCIPAL_TYPE) === ApiPrincipalType::User->value
                ? null
                : throw new MissingScopeException(Registrar::OAUTH_SCOPE),
        };

        return $next($request);
    }
}
