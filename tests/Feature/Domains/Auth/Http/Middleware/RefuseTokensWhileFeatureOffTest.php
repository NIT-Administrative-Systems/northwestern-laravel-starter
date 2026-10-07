<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Middleware;

use App\Domains\Auth\Actions\Api\CreateServiceClient;
use App\Domains\Auth\Http\Middleware\RefuseClientCredentialsWhileApiDisabled;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\IssuesServiceClientTokens;
use Tests\TestCase;

#[CoversClass(RefuseClientCredentialsWhileApiDisabled::class)]
final class RefuseClientCredentialsWhileApiDisabledTest extends TestCase
{
    use IssuesServiceClientTokens;

    public function test_service_clients_get_tokens_while_the_api_is_on(): void
    {
        [$token] = $this->serviceClientToken(User::factory()->api()->create());

        $this->assertNotEmpty($token);
    }

    public function test_the_client_credentials_grant_is_refused_while_the_api_is_off(): void
    {
        [$secret, $client] = resolve(CreateServiceClient::class)(User::factory()->api()->create(), 'Sync', now()->addMonth());
        config(['api.enabled' => false]);

        $this->postJson('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->getKey(),
            'client_secret' => $secret,
        ])
            ->assertBadRequest()
            ->assertJsonPath('error', 'unsupported_grant_type')
            ->assertJsonMissingPath('access_token');
    }

    // MCP clients use the same endpoint for the authorization-code and refresh grants.
    public function test_other_grants_reach_passport_while_the_api_is_off(): void
    {
        config(['api.enabled' => false]);

        $error = $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => 'not-a-token'])->json('error');

        $this->assertNotSame('unsupported_grant_type', $error);
    }
}
