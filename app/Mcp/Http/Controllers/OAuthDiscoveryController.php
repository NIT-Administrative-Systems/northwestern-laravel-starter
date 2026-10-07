<?php

declare(strict_types=1);

namespace App\Mcp\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Laravel\Mcp\Server\Registrar;

/**
 * The OAuth discovery documents an MCP client reads to connect: where the MCP server's
 * authorization comes from (RFC 9728), and that authorization server's endpoints (RFC 8414).
 * A client starts from the `WWW-Authenticate` header on the server's 401 response.
 */
class OAuthDiscoveryController
{
    public function protectedResource(): JsonResponse
    {
        return response()->json([
            'resource' => url('/mcp'),
            'resource_name' => config('app.name'),
            'authorization_servers' => [$this->issuer()],
            'scopes_supported' => [Registrar::OAUTH_SCOPE],
            'bearer_methods_supported' => ['header'],
        ]);
    }

    public function authorizationServer(): JsonResponse
    {
        return response()->json([
            'issuer' => $this->issuer(),
            'authorization_endpoint' => route('passport.authorizations.authorize'),
            'token_endpoint' => route('passport.token'),
            'registration_endpoint' => route('mcp.oauth.register'),
            'scopes_supported' => [Registrar::OAUTH_SCOPE],
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'code_challenge_methods_supported' => ['S256'],
        ]);
    }

    private function issuer(): string
    {
        $issuer = config('mcp.authorization_server');

        return is_string($issuer) && $issuer !== '' ? $issuer : url('/');
    }
}
