<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\OAuth;

use App\Domains\Auth\Enums\ClientOrigin;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Http\Controllers\McpDiscoveryController;
use App\Domains\Auth\Http\Controllers\RegisterMcpClientController;
use App\Domains\Auth\Http\Middleware\RefuseMcpTokens;
use App\Domains\Auth\Http\Middleware\RequireMcpAccess;
use App\Domains\Auth\Http\Middleware\RequireMcpPermissionForConsent;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\Auth\Models\OAuthConnection;
use App\Domains\User\Models\User;
use App\Mcp\Servers\AppServer;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\IssuesPersonalAccessTokens;
use Tests\Concerns\IssuesServiceClientTokens;
use Tests\Concerns\RunsAuthorizationCodeFlow;
use Tests\Fixtures\Mcp\EchoServer;
use Tests\TestCase;

/**
 * An MCP client connecting the way Claude, VS Code and Cursor do: from the server's 401, through
 * discovery, self-registration and consent, to calling a tool.
 */
#[CoversClass(McpDiscoveryController::class)]
#[CoversClass(RegisterMcpClientController::class)]
#[CoversClass(RequireMcpAccess::class)]
#[CoversClass(RefuseMcpTokens::class)]
#[CoversClass(RequireMcpPermissionForConsent::class)]
#[CoversClass(AppServer::class)]
final class McpFlowTest extends TestCase
{
    use IssuesPersonalAccessTokens, IssuesServiceClientTokens, RunsAuthorizationCodeFlow;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mcp.enabled' => true]);
        // The starter's server ships with no tools; this one adds a tool to call.
        $this->app->bind(AppServer::class, EchoServer::class);
    }

    public function test_a_client_discovers_registers_connects_and_calls_a_tool(): void
    {
        $user = $this->mcpUser();

        $this->mcp(null, 'initialize')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="' . url('/.well-known/oauth-protected-resource/mcp') . '"')
            // MCP clients expect the protocol's errors, not the REST API's Problem Details.
            ->assertHeader('Content-Type', 'application/json');

        $this->getJson('/.well-known/oauth-protected-resource/mcp')
            ->assertOk()
            ->assertJsonPath('resource', url('/mcp'))
            ->assertJsonPath('authorization_servers', [url('/')])
            ->assertJsonPath('scopes_supported', ['mcp:use']);
        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('issuer', url('/'))
            ->assertJsonPath('registration_endpoint', url('/oauth/register'))
            ->assertJsonPath('token_endpoint_auth_methods_supported', ['none'])
            ->assertJsonPath('code_challenge_methods_supported', ['S256']);

        $this->actingAs($user);
        $client = $this->registerMcpClient('Claude Code');
        $this->assertSame(ClientOrigin::Dynamic, $client->origin);
        $this->assertFalse($client->confidential());
        $this->assertSame(['mcp:use'], $client->scopes);

        [$consent, $verifier] = $this->requestAuthorization($client, ['mcp:use']);
        $consent->assertOk()
            ->assertSee('Connect Claude Code')
            ->assertSee('Unverified AI client.')
            ->assertSee("Use this application's tools from an AI client")
            ->assertDontSee('See your account details');

        $token = $this->exchange($client, $this->approve($client), $verifier)->assertOk()->json('access_token');

        $this->mcp($token, 'tools/call', ['name' => 'echo', 'arguments' => ['text' => 'hello']])
            ->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent', ['text' => 'hello', 'person' => $user->full_name]);
        $this->assertSame(['mcp:use'], OAuthConnection::query()->sole()->scopes);
    }

    public function test_registration_accepts_only_https_loopback_and_configured_app_schemes(): void
    {
        $register = fn (array $uris, ?string $name = 'Client') => $this->postJson('/oauth/register', ['client_name' => $name, 'redirect_uris' => $uris]);

        // No app scheme is allowed until one is configured.
        $register(['cursor://anysphere.cursor-mcp/oauth/callback'])->assertBadRequest();
        config(['mcp.custom_schemes' => ['cursor']]);

        $register(['https://claude.ai/api/mcp/auth_callback'])->assertCreated();
        $register(['http://127.0.0.1:6274/oauth/callback'])->assertCreated();
        $register(['cursor://anysphere.cursor-mcp/oauth/callback'])->assertCreated();
        $this->assertSame('example.edu', $register(['https://example.edu/callback'], null)->json('client_name'));

        foreach ([['http://example.edu/callback'], ['https://example.edu/callback#fragment'], ['https://user:pass@example.edu/callback'], ['vscode://vscode.github-authentication/did-authenticate'], []] as $uris) {
            $register($uris)->assertBadRequest()->assertJsonPath('error', 'invalid_redirect_uri');
        }

        $this->postJson('/oauth/register', ['client_name' => ['not a string'], 'redirect_uris' => ['https://example.edu/callback']])
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_client_metadata');
        $this->assertSame(4, OAuthClient::query()->where('origin', ClientOrigin::Dynamic)->count());
    }

    public function test_registration_is_limited_per_ip_address(): void
    {
        config(['mcp.rate_limits.registrations_per_hour' => 2]);

        $this->registerMcpClient();
        $this->registerMcpClient();

        $this->postJson('/oauth/register', ['redirect_uris' => [self::REDIRECT_URI]])->assertTooManyRequests();
    }

    public function test_an_mcp_token_is_refused_on_the_rest_api(): void
    {
        $user = $this->mcpUser([SystemPermission::CreatePersonalAccessTokens]);
        [$personal] = $this->personalAccessToken($user);
        $this->actingAs($user);
        $token = $this->mcpToken();

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('detail', 'MCP tokens can only be used with the MCP server.');
        $this->withToken($personal)->getJson('/api/v1/me')->assertOk();
    }

    public function test_service_client_personal_and_application_tokens_are_refused_on_the_mcp_server(): void
    {
        $user = $this->mcpUser([SystemPermission::ViewUsers, SystemPermission::CreatePersonalAccessTokens]);
        [$personal] = $this->personalAccessToken($user, ['view-users']);
        [$service] = $this->serviceClientToken(User::factory()->api()->create());

        $this->actingAs($user);
        $application = $this->registerApplication();
        [, $verifier] = $this->requestAuthorization($application);
        $delegated = $this->exchange($application, $this->approve($application), $verifier)->json('access_token');

        foreach ([$personal, $service, $delegated] as $token) {
            $this->mcp($token, 'tools/list')->assertForbidden();
        }
    }

    // Losing the permission ends access at once; oauth:revoke-ineligible then disconnects the client.
    public function test_only_people_with_the_use_mcp_permission_connect_and_keep_using_a_client(): void
    {
        $this->actingAs(User::factory()->create());
        $client = $this->registerMcpClient();
        [$consent] = $this->requestAuthorization($client, ['mcp:use']);
        $consent->assertForbidden();

        $user = $this->mcpUser();
        $this->actingAs($user);
        $token = $this->mcpToken();
        $this->mcp($token, 'tools/list')->assertOk();

        $user->revokePermissionTo(SystemPermission::UseMcp);

        $this->mcp($token, 'tools/list')->assertForbidden();
    }

    public function test_everything_answers_404_while_mcp_is_disabled(): void
    {
        $this->actingAs($this->mcpUser());
        $client = $this->registerMcpClient();

        config(['mcp.enabled' => false]);

        $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertNotFound();
        $this->getJson('/.well-known/oauth-authorization-server')->assertNotFound();
        $this->postJson('/oauth/register', ['redirect_uris' => [self::REDIRECT_URI]])->assertNotFound();
        $this->mcp(null, 'initialize')->assertNotFound();
        [$consent] = $this->requestAuthorization($client, ['mcp:use']);
        $consent->assertNotFound();
    }

    // Listing tools and pinging only count towards the per-IP limit.
    public function test_tool_calls_are_limited_per_person(): void
    {
        config(['mcp.rate_limits.tool_calls_per_minute' => 1]);
        $this->actingAs($this->mcpUser());
        $token = $this->mcpToken();
        $call = fn () => $this->mcp($token, 'tools/call', ['name' => 'echo', 'arguments' => ['text' => 'hello']]);

        $call()->assertOk();
        $call()->assertTooManyRequests();
        $this->mcp($token, 'tools/list')->assertOk();
    }

    /**
     * @param  list<SystemPermission>  $permissions
     */
    private function mcpUser(array $permissions = []): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::UseMcp, ...$permissions);

        return $user;
    }
}
