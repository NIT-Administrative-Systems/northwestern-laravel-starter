<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Middleware;

use App\Domains\Auth\Actions\Api\CreateServiceClient;
use App\Domains\Auth\Actions\Applications\RegisterOAuthApplication;
use App\Domains\Auth\Enums\ClientOrigin;
use App\Domains\Auth\Http\Middleware\RefuseTokensWhileFeatureOff;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\IssuesServiceClientTokens;
use Tests\TestCase;

#[CoversClass(RefuseTokensWhileFeatureOff::class)]
final class RefuseTokensWhileFeatureOffTest extends TestCase
{
    use IssuesServiceClientTokens;

    public function test_service_clients_get_tokens_while_the_api_is_on(): void
    {
        [$token] = $this->serviceClientToken(User::factory()->api()->create());

        $this->assertNotEmpty($token);
    }

    public function test_a_service_client_gets_no_token_while_the_api_is_off(): void
    {
        [$secret, $client] = resolve(CreateServiceClient::class)(User::factory()->api()->create(), 'Sync', now()->addMonth());
        config(['api.enabled' => false]);

        $this->postJson('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->getKey(),
            'client_secret' => $secret,
        ])
            ->assertBadRequest()
            ->assertJsonPath('error', 'unauthorized_client')
            ->assertJsonPath('error_description', 'Service clients are turned off.')
            ->assertJsonMissingPath('access_token');
    }

    // A confidential client may send its ID as the HTTP Basic username instead of in the body.
    public function test_a_client_identified_by_basic_auth_is_refused_too(): void
    {
        [$secret, $client] = resolve(CreateServiceClient::class)(User::factory()->api()->create(), 'Sync', now()->addMonth());
        config(['api.enabled' => false]);

        $this->withBasicAuth((string) $client->getKey(), $secret)
            ->postJson('/oauth/token', ['grant_type' => 'client_credentials'])
            ->assertBadRequest()
            ->assertJsonPath('error', 'unauthorized_client');
    }

    // An MCP client follows mcp.enabled: while MCP is off it can't refresh, whatever the API does.
    public function test_an_mcp_client_cannot_refresh_while_mcp_is_off(): void
    {
        [, $client] = resolve(RegisterOAuthApplication::class)('Claude', ['http://localhost/cb'], false, [], origin: ClientOrigin::Dynamic);
        config(['api.enabled' => true, 'mcp.enabled' => false]);

        $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $client->getKey(), 'refresh_token' => 'not-a-token'])
            ->assertBadRequest()
            ->assertJsonPath('error_description', 'MCP clients are turned off.');
    }

    public function test_an_unknown_client_is_left_to_passport(): void
    {
        config(['api.enabled' => false]);

        $error = $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => 'not-a-uuid', 'refresh_token' => 'not-a-token'])->json('error');

        $this->assertNotSame('unauthorized_client', $error);
    }

    // It sits on every Passport route, and only the token endpoint issues tokens.
    public function test_other_passport_routes_pass_through(): void
    {
        config(['api.enabled' => false]);

        $this->assertNotSame('unauthorized_client', $this->get('/oauth/authorize?client_id=not-a-uuid')->json('error'));
    }
}
