<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Domains\Api\Actions\Applications\RegisterOAuthApplication;
use App\Domains\Api\Models\OAuthClient;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Drives the OAuth authorization code flow with PKCE as a public application does:
 * the authorization request, the consent screen, approval and the code exchange.
 *
 * @mixin TestCase
 */
trait RunsAuthorizationCodeFlow
{
    protected const string REDIRECT_URI = 'http://localhost:4100/callback';

    /** Claude Code's client metadata document, which it sends as its client ID. */
    protected const string CLAUDE_CODE_METADATA_URL = 'https://claude.ai/oauth/claude-code-client-metadata';

    /**
     * Serve Claude Code's client metadata document as claude.ai does.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function fakeClientMetadataDocument(array $overrides = []): void
    {
        Http::preventStrayRequests();
        Http::fake([self::CLAUDE_CODE_METADATA_URL => Http::response([
            'client_id' => self::CLAUDE_CODE_METADATA_URL,
            'client_name' => 'Claude Code',
            'client_uri' => 'https://claude.ai',
            'redirect_uris' => ['http://localhost/callback', 'http://127.0.0.1/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            ...$overrides,
        ])]);
    }

    /**
     * @param  list<string>  $scopes  The scopes the application may request
     */
    protected function registerApplication(array $scopes = ['view-users'], bool $firstParty = false): OAuthClient
    {
        [, $client] = resolve(RegisterOAuthApplication::class)('Reporting Tool', [self::REDIRECT_URI], false, $scopes, $firstParty);

        return $client;
    }

    /**
     * @param  OAuthClient|string  $client  The client, or the client ID to send, such as a metadata document's URL
     * @param  list<string>  $scopes
     * @return array{0: TestResponse<\Symfony\Component\HttpFoundation\Response>, 1: string} The authorization response and the PKCE verifier
     */
    protected function requestAuthorization(OAuthClient|string $client, array $scopes = ['view-users'], string $state = 'state-123', string $redirectUri = self::REDIRECT_URI): array
    {
        $verifier = Str::random(64);

        $response = $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => is_string($client) ? $client : $client->getKey(),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]));

        return [$response, $verifier];
    }

    /**
     * Approve the consent screen the last request showed, and return the code.
     */
    protected function approveAuthorization(OAuthClient $client): string
    {
        $response = $this->post('/oauth/authorize', [
            'client_id' => $client->getKey(),
            'auth_token' => session('authToken'),
        ]);

        return $this->authorizationCodeFrom($response);
    }

    /**
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    protected function authorizationCodeFrom(TestResponse $response): string
    {
        $response->assertRedirect();
        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

        return (string) ($query['code'] ?? '');
    }

    /**
     * Register an MCP client at the registration endpoint, as one registers itself.
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
    protected function issueMcpToken(): string
    {
        $client = $this->registerMcpClient();
        [, $verifier] = $this->requestAuthorization($client, ['mcp:use']);

        return (string) $this->exchangeAuthorizationCode($client, $this->approveAuthorization($client), $verifier)->assertOk()->json('access_token');
    }

    /**
     * Connect Claude Code through its client metadata document as the signed-in
     * person and return the access token. They need the Use MCP permission.
     */
    protected function issueMcpTokenWithMetadataDocument(): string
    {
        $this->fakeClientMetadataDocument();
        [, $verifier] = $this->requestAuthorization(self::CLAUDE_CODE_METADATA_URL, ['mcp:use']);
        $client = OAuthClient::query()->where('client_metadata_url', self::CLAUDE_CODE_METADATA_URL)->sole();

        return (string) $this->exchangeAuthorizationCode(self::CLAUDE_CODE_METADATA_URL, $this->approveAuthorization($client), $verifier)->assertOk()->json('access_token');
    }

    /**
     * Send one JSON-RPC message to the MCP server.
     *
     * @param  array<string, mixed>  $params
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    protected function sendMcpMessage(?string $token, string $method, array $params = []): TestResponse
    {
        $headers = ['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'];

        if ($token !== null) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params], $headers);
    }

    /**
     * @param  OAuthClient|string  $client  The client, or the client ID to send, such as a metadata document's URL
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    protected function exchangeAuthorizationCode(OAuthClient|string $client, string $code, string $verifier, string $redirectUri = self::REDIRECT_URI): TestResponse
    {
        return $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => is_string($client) ? $client : $client->getKey(),
            'redirect_uri' => $redirectUri,
            'code_verifier' => $verifier,
            'code' => $code,
        ]);
    }
}
