<?php

declare(strict_types=1);

namespace App\Mcp\Http\Controllers;

use App\Domains\Api\Actions\Applications\RegisterOAuthApplication;
use App\Domains\Api\Enums\ClientOrigin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Registrar;
use Northwestern\SysDev\Chassis\Rules\OAuthRedirectUri;

/**
 * OAuth dynamic client registration (RFC 7591) for MCP clients. Anyone may register, so a
 * client gets nothing until a person with the `UseMcp` permission approves it: it is public
 * (PKCE, no secret), may ask only for `mcp:use`, and its self-reported name is marked
 * unverified on the consent screen. `mcp:prune-clients` removes clients nobody connects.
 */
class RegisterClientController
{
    public function __invoke(Request $request, RegisterOAuthApplication $register): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_name' => ['nullable', 'string', 'max:255'],
            'redirect_uris' => ['required', 'array', 'min:1', 'max:10'],
            'redirect_uris.*' => ['bail', 'required', 'string', 'max:2048', new OAuthRedirectUri(config('mcp.custom_schemes', []))],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();

            return response()->json([
                'error' => $errors->has('redirect_uris') || $errors->has('redirect_uris.*') ? 'invalid_redirect_uri' : 'invalid_client_metadata',
                'error_description' => $errors->first(),
            ], 400);
        }

        /** @var list<non-empty-string> $redirectUris */
        $redirectUris = array_values($validator->validated()['redirect_uris']);

        [, $client] = $register(
            name: $this->name($request->string('client_name')->trim()->toString(), $redirectUris[0]),
            redirectUris: $redirectUris,
            confidential: false,
            scopes: [Registrar::OAUTH_SCOPE],
            origin: ClientOrigin::Dynamic,
        );

        return response()->json([
            'client_id' => $client->getKey(),
            'client_name' => $client->name,
            'client_id_issued_at' => $client->created_at?->getTimestamp(),
            'redirect_uris' => $client->redirect_uris,
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'scope' => Registrar::OAUTH_SCOPE,
        ], 201);
    }

    /**
     * @return non-empty-string
     */
    private function name(string $clientName, string $redirectUri): string
    {
        if ($clientName !== '') {
            return Str::limit($clientName, 255, '');
        }

        $host = parse_url($redirectUri, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'MCP client';
    }
}
