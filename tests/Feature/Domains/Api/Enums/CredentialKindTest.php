<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Api\Enums;

use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Auth\Actions\Api\CreateServiceClient;
use App\Domains\Auth\Actions\Applications\RegisterOAuthApplication;
use App\Domains\Auth\Enums\ClientOrigin;
use App\Domains\User\Models\User;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\IssuesPersonalAccessTokens;
use Tests\TestCase;

#[CoversClass(CredentialKind::class)]
final class CredentialKindTest extends TestCase
{
    use IssuesPersonalAccessTokens;

    public function test_one_passport_model_is_told_apart_by_kind(): void
    {
        [, $serviceClient] = resolve(CreateServiceClient::class)(User::factory()->api()->create(), 'Sync', now()->addMonth());
        [, $application] = resolve(RegisterOAuthApplication::class)('Portal', ['https://portal.example.edu/cb'], true, []);
        [, $mcpClient] = resolve(RegisterOAuthApplication::class)('Claude', ['http://localhost/cb'], false, [], origin: ClientOrigin::Dynamic);

        $this->assertSame(CredentialKind::ServiceClient, CredentialKind::of($serviceClient));
        $this->assertSame(CredentialKind::ConnectedApplication, CredentialKind::of($application));
        $this->assertSame(CredentialKind::McpClient, CredentialKind::of($mcpClient));
    }

    public function test_a_token_takes_its_clients_kind(): void
    {
        [, $token] = $this->personalAccessToken(User::factory()->affiliate()->create());
        $this->assertSame(CredentialKind::PersonalAccessToken, CredentialKind::of($token));

        $token->client()->delete();
        $token->unsetRelation('client');

        $this->expectException(InvalidArgumentException::class);
        CredentialKind::of($token);
    }

    public function test_mcp_clients_follow_mcp_enabled_and_the_rest_follow_api_enabled(): void
    {
        config(['api.enabled' => true, 'mcp.enabled' => false]);

        $this->assertFalse(CredentialKind::McpClient->isEnabled());
        $this->assertTrue(CredentialKind::ServiceClient->isEnabled());

        config(['api.enabled' => false, 'mcp.enabled' => true]);

        $this->assertTrue(CredentialKind::McpClient->isEnabled());
        $this->assertFalse(CredentialKind::PersonalAccessToken->isEnabled());
        $this->assertFalse(CredentialKind::ConnectedApplication->isEnabled());
    }

    public function test_each_kind_names_itself_in_a_sentence(): void
    {
        $this->assertSame(
            ['personal access tokens', 'connected applications', 'MCP clients', 'service clients'],
            array_map(fn (CredentialKind $kind): string => $kind->plural(), CredentialKind::cases()),
        );
    }
}
