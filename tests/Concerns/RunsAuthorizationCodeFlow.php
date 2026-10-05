<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Domains\Auth\Actions\Applications\RegisterOAuthApplication;
use App\Domains\Auth\Models\OAuthClient;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Drives the OAuth authorization code flow with PKCE as a public application would: the
 * authorization request, the consent screen, approval, and the code exchange.
 *
 * @mixin TestCase
 */
trait RunsAuthorizationCodeFlow
{
    protected const string REDIRECT_URI = 'http://localhost:4100/callback';

    /**
     * @param  list<string>  $scopes  The scopes the application may request
     */
    protected function registerApplication(array $scopes = ['view-users'], bool $firstParty = false): OAuthClient
    {
        [, $client] = resolve(RegisterOAuthApplication::class)('Reporting Tool', [self::REDIRECT_URI], false, $scopes, $firstParty);

        return $client;
    }

    /**
     * @param  list<string>  $scopes
     * @return array{0: TestResponse<\Symfony\Component\HttpFoundation\Response>, 1: string} The authorization response and the PKCE verifier
     */
    protected function requestAuthorization(OAuthClient $client, array $scopes = ['view-users'], string $state = 'state-123'): array
    {
        $verifier = Str::random(64);

        $response = $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]));

        return [$response, $verifier];
    }

    /**
     * Approve the consent screen the last authorization request showed. Returns the code.
     */
    protected function approve(OAuthClient $client): string
    {
        $response = $this->post('/oauth/authorize', [
            'state' => 'state-123',
            'client_id' => $client->getKey(),
            'auth_token' => session('authToken'),
        ]);

        return $this->codeFrom($response);
    }

    /**
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    protected function codeFrom(TestResponse $response): string
    {
        $response->assertRedirect();
        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

        return (string) ($query['code'] ?? '');
    }

    /**
     * Register an MCP client the way one registers itself, at the dynamic registration endpoint.
     */
    protected function registerMcpClient(string $name = 'Claude Code'): OAuthClient
    {
        $clientId = $this->postJson('/oauth/register', ['client_name' => $name, 'redirect_uris' => [self::REDIRECT_URI]])
            ->assertCreated()
            ->json('client_id');

        return OAuthClient::query()->findOrFail($clientId);
    }

    /**
     * Connect a new MCP client as the signed-in person and return its access token.
     */
    protected function mcpToken(): string
    {
        $client = $this->registerMcpClient();
        [, $verifier] = $this->requestAuthorization($client, ['mcp:use']);

        return (string) $this->exchange($client, $this->approve($client), $verifier)->assertOk()->json('access_token');
    }

    /**
     * Send one JSON-RPC message to the MCP server.
     *
     * @param  array<string, mixed>  $params
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    protected function mcp(?string $token, string $method, array $params = []): TestResponse
    {
        $headers = ['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'];

        if ($token !== null) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params], $headers);
    }

    /**
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    protected function exchange(OAuthClient $client, string $code, string $verifier): TestResponse
    {
        return $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'redirect_uri' => self::REDIRECT_URI,
            'code_verifier' => $verifier,
            'code' => $code,
        ]);
    }
}
