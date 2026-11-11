<?php

declare(strict_types=1);

namespace App\Domains\Api\Http\Middleware;

use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\TokenAudience;
use App\Domains\Api\Models\OAuthClient;
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
 * Keeps each token on its own side of the application, after
 * {@see AuthenticatePassportToken} has authenticated it.
 * The kind of client the token was issued to decides
 * which side it's on, not the scopes it carries.
 *
 * A token issued to an MCP client works only on the MCP server, so
 * a client connected for its tools can't reach the REST API. The
 * MCP server admits only those tokens, holding `mcp:use`, so
 * service client, personal access and application tokens
 * can't call tools. Whether the person may still use
 * MCP is decided when the token is authenticated.
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
        $client = OAuthClient::query()->find(Context::get(ApiRequestContext::OAUTH_CLIENT_ID));
        $isMcpClient = $client instanceof OAuthClient && CredentialKind::of($client) === CredentialKind::McpClient;
        $scopes = Context::get(ApiRequestContext::OAUTH_SCOPES);

        match (TokenAudience::from($audience)) {
            TokenAudience::Api => $isMcpClient
                ? throw new AuthorizationException('MCP tokens can only be used with the MCP server.')
                : null,
            TokenAudience::Mcp => $isMcpClient
                && is_array($scopes) && in_array(Registrar::OAUTH_SCOPE, $scopes, true)
                && Context::get(ApiRequestContext::PRINCIPAL_TYPE) === ApiPrincipalType::User->value
                ? null
                : throw new MissingScopeException(Registrar::OAUTH_SCOPE),
        };

        return $next($request);
    }
}
