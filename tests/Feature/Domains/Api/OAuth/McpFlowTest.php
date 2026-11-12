<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Api\OAuth;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Api\Enums\ClientOrigin;
use App\Domains\Api\Http\Middleware\AuthorizeOAuthConsent;
use App\Domains\Api\Http\Middleware\RequireTokenAudience;
use App\Domains\Api\Models\OAuthClient;
use App\Domains\Api\Models\OAuthConnection;
use App\Domains\Api\Passport\OAuthClientRepository;
use App\Domains\Api\Passport\OAuthConsent;
use App\Domains\User\Models\User;
use App\Mcp\ClientMetadataDocuments;
use App\Mcp\Http\Controllers\OAuthDiscoveryController;
use App\Mcp\Http\Controllers\RegisterClientController;
use App\Mcp\Http\Middleware\AddScopeToChallenge;
use App\Mcp\McpServiceProvider;
use App\Mcp\Servers\AppServer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\IssuesPersonalAccessTokens;
use Tests\Concerns\IssuesServiceClientTokens;
use Tests\Concerns\RunsAuthorizationCodeFlow;
use Tests\Fixtures\Mcp\EchoServer;
use Tests\TestCase;

/**
 * An MCP client connecting as Claude, VS Code and Cursor do: from the server's
 * 401, through discovery, self-registration and consent, to calling a tool.
 */
#[CoversClass(AddScopeToChallenge::class)]
#[CoversClass(OAuthDiscoveryController::class)]
#[CoversClass(RegisterClientController::class)]
#[CoversClass(RequireTokenAudience::class)]
#[CoversClass(McpServiceProvider::class)]
#[CoversClass(AuthorizeOAuthConsent::class)]
#[CoversClass(OAuthConsent::class)]
#[CoversClass(AppServer::class)]
#[CoversClass(OAuthClientRepository::class)]
#[CoversClass(ClientMetadataDocuments::class)]
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

        $this->sendMcpMessage(null, 'initialize')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="' . url('/.well-known/oauth-protected-resource/mcp') . '", scope="mcp:use"')
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
            ->assertJsonPath('code_challenge_methods_supported', ['S256'])
            ->assertJsonPath('client_id_metadata_document_supported', true);

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

        $token = $this->exchangeAuthorizationCode($client, $this->approveAuthorization($client), $verifier)->assertOk()->json('access_token');

        $this->sendMcpMessage($token, 'tools/call', ['name' => 'echo', 'arguments' => ['text' => 'hello']])
            ->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent', ['text' => 'hello', 'person' => $user->full_name]);
        $this->assertSame(['mcp:use'], OAuthConnection::query()->sole()->scopes);
    }

    // Claude sends the URL of the metadata document it publishes as its client ID, on
    // every request, and returns to whichever localhost port it opened.
    public function test_claude_connects_with_its_client_metadata_document(): void
    {
        $this->fakeClientMetadataDocument();
        $user = $this->mcpUser();
        $this->actingAs($user);

        [$consent, $verifier] = $this->requestAuthorization(self::CLAUDE_CODE_METADATA_URL, ['mcp:use']);
        $consent->assertOk()
            ->assertSee('Connect Claude Code')
            ->assertSee('Verified AI client.')
            ->assertSee('Its name comes from claude.ai')
            ->assertDontSee('Unverified AI client.');

        $client = OAuthClient::query()->where('client_metadata_url', self::CLAUDE_CODE_METADATA_URL)->sole();
        $tokens = $this->exchangeAuthorizationCode(self::CLAUDE_CODE_METADATA_URL, $this->approveAuthorization($client), $verifier)->assertOk()->json();

        $this->sendMcpMessage($tokens['access_token'], 'tools/call', ['name' => 'echo', 'arguments' => ['text' => 'hello']])
            ->assertOk()
            ->assertJsonPath('result.structuredContent', ['text' => 'hello', 'person' => $user->full_name]);
        $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => self::CLAUDE_CODE_METADATA_URL, 'refresh_token' => $tokens['refresh_token']])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'refresh_token']);
        $this->assertSame($client->getKey(), OAuthConnection::query()->sole()->oauth_client_id);
    }

    public function test_metadata_documents_are_not_advertised_without_a_host_to_fetch_them_from(): void
    {
        config(['mcp.client_metadata_hosts' => []]);

        $this->getJson('/.well-known/oauth-authorization-server')->assertJsonPath('client_id_metadata_document_supported', false);
    }

    // The consent check finds the client by its document's URL, as Passport does.
    public function test_consent_refuses_a_metadata_document_client_to_someone_without_the_use_mcp_permission(): void
    {
        $this->fakeClientMetadataDocument();
        $this->actingAs(User::factory()->create());

        [$consent] = $this->requestAuthorization(self::CLAUDE_CODE_METADATA_URL, ['mcp:use']);

        $consent->assertForbidden();
    }

    public function test_a_document_on_an_unlisted_host_is_an_unknown_client(): void
    {
        Http::preventStrayRequests();
        $this->actingAs($this->mcpUser());

        [$consent] = $this->requestAuthorization('https://claude.example/oauth/claude-code-client-metadata', ['mcp:use']);

        $consent->assertBadRequest()->assertSee('Application Not Registered');
        $this->assertSame(0, OAuthClient::query()->count());
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
        [$personal] = $this->issuePersonalAccessToken($user);
        $this->actingAs($user);
        $token = $this->issueMcpToken();

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('detail', 'MCP tokens can only be used with the MCP server.');
        $this->withToken($personal)->getJson('/api/v1/me')->assertOk();
    }

    // The client's kind decides where its token works, not the scopes it carries.
    public function test_an_mcp_clients_token_without_the_mcp_scope_is_refused_on_the_rest_api(): void
    {
        $user = $this->mcpUser([SystemPermission::ViewUsers]);
        $this->actingAs($user);
        $client = $this->registerMcpClient();

        // Consent now refuses this request, so skip it to get the token it once gave.
        $this->withoutMiddleware(AuthorizeOAuthConsent::class);
        [, $verifier] = $this->requestAuthorization($client, []);
        $token = (string) $this->exchangeAuthorizationCode($client, $this->approveAuthorization($client), $verifier)->assertOk()->json('access_token');

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('detail', 'MCP tokens can only be used with the MCP server.');
        $this->sendMcpMessage($token, 'tools/list')->assertForbidden();
    }

    public function test_consent_refuses_an_mcp_client_that_does_not_ask_for_the_mcp_scope(): void
    {
        $this->actingAs($this->mcpUser());
        $client = $this->registerMcpClient();

        [$consent] = $this->requestAuthorization($client, []);
        $consent->assertForbidden();

        // Nor can approving a request without it.
        $this->requestAuthorization($client, ['mcp:use'])[0]->assertOk();
        $authRequest = unserialize((string) session('authRequest'));
        $authRequest->setScopes([]);
        session(['authRequest' => serialize($authRequest)]);
        $this->post('/oauth/authorize', ['client_id' => $client->getKey(), 'auth_token' => session('authToken')])->assertForbidden();
    }

    public function test_service_client_personal_and_application_tokens_are_refused_on_the_mcp_server(): void
    {
        $user = $this->mcpUser([SystemPermission::ViewUsers, SystemPermission::CreatePersonalAccessTokens]);
        [$personal] = $this->issuePersonalAccessToken($user, ['view-users']);
        [$service] = $this->issueServiceClientToken(User::factory()->api()->create());

        $this->actingAs($user);
        $application = $this->registerApplication();
        [, $verifier] = $this->requestAuthorization($application);
        $delegated = $this->exchangeAuthorizationCode($application, $this->approveAuthorization($application), $verifier)->json('access_token');

        foreach ([$personal, $service, $delegated] as $token) {
            $this->sendMcpMessage($token, 'tools/list')->assertForbidden();
        }
    }

    // Losing the permission ends access at once: the token stops
    // working and the consent screen refuses the client's next
    // sign-in. oauth:revoke-ineligible then disconnects it.
    public function test_only_people_with_the_use_mcp_permission_connect_and_keep_using_a_client(): void
    {
        $this->actingAs(User::factory()->create());
        $client = $this->registerMcpClient();
        [$consent] = $this->requestAuthorization($client, ['mcp:use']);
        $consent->assertForbidden();

        $user = $this->mcpUser();
        $this->actingAs($user);
        $token = $this->issueMcpToken();
        $this->sendMcpMessage($token, 'tools/list')->assertOk();

        $user->revokePermissionTo(SystemPermission::UseMcp);

        $this->sendMcpMessage($token, 'tools/list')->assertUnauthorized();
    }

    public function test_everything_answers_404_while_mcp_is_disabled(): void
    {
        $this->actingAs($this->mcpUser());
        $client = $this->registerMcpClient();

        config(['mcp.enabled' => false]);

        $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertNotFound();
        $this->getJson('/.well-known/oauth-authorization-server')->assertNotFound();
        $this->postJson('/oauth/register', ['redirect_uris' => [self::REDIRECT_URI]])->assertNotFound();
        $this->sendMcpMessage(null, 'initialize')->assertNotFound();
        [$consent] = $this->requestAuthorization($client, ['mcp:use']);
        $consent->assertNotFound();
    }

    // Listing tools and pinging only count towards the per-IP limit.
    public function test_tool_calls_are_limited_per_person(): void
    {
        config(['mcp.rate_limits.tool_calls_per_minute' => 1]);
        $this->actingAs($this->mcpUser());
        $token = $this->issueMcpToken();
        $call = fn () => $this->sendMcpMessage($token, 'tools/call', ['name' => 'echo', 'arguments' => ['text' => 'hello']]);

        $call()->assertOk();
        $call()->assertTooManyRequests();
        $this->sendMcpMessage($token, 'tools/list')->assertOk();
    }

    public function test_one_persons_tool_calls_leave_another_persons_limit_alone(): void
    {
        config(['mcp.rate_limits.tool_calls_per_minute' => 1]);
        $this->actingAs($this->mcpUser());
        $firstToken = $this->issueMcpToken();
        $this->actingAs($this->mcpUser());
        $secondToken = $this->issueMcpToken();
        // An MCP client carries only its token, never a session.
        Auth::forgetGuards();
        $call = fn (string $token) => $this->sendMcpMessage($token, 'tools/call', ['name' => 'echo', 'arguments' => ['text' => 'hello']]);

        $call($firstToken)->assertOk();
        $call($secondToken)->assertOk();
        $call($firstToken)->assertTooManyRequests();
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
