<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Actions\Api;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Auth\Actions\Api\RevokeServiceClient;
use App\Domains\Auth\Enums\CredentialStatus;
use App\Domains\Core\Models\Audit;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\IssuesServiceClientTokens;
use Tests\TestCase;

#[CoversClass(RevokeServiceClient::class)]
final class RevokeServiceClientTest extends TestCase
{
    use IssuesServiceClientTokens;

    // An integration loses access at once, not when its current token expires.
    public function test_it_revokes_the_client_and_the_tokens_it_holds(): void
    {
        [$token, $client] = $this->serviceClientToken(User::factory()->api()->create());
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();

        resolve(RevokeServiceClient::class)($client);

        $this->assertSame(CredentialStatus::Revoked, $client->fresh()?->status);
        $this->assertSame(0, Passport::token()->newQuery()->where('client_id', $client->getKey())->where('revoked', false)->count());
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
        $this->assertSame($client->getKey(), Audit::query()->where('event', 'service_client_revoked')->sole()->new_values['client_id']);
    }

    public function test_an_administrator_needs_manage_api_access(): void
    {
        [, $client] = $this->serviceClientToken(User::factory()->api()->create());

        try {
            resolve(RevokeServiceClient::class)($client, User::factory()->affiliate()->create());
            $this->fail('A service client was revoked without Manage API Access.');
        } catch (AuthorizationException) {
            $this->assertSame(CredentialStatus::Active, $client->refresh()->status);
        }

        $administrator = User::factory()->affiliate()->create();
        $administrator->givePermissionTo(SystemPermission::ManageApiAccess);

        resolve(RevokeServiceClient::class)($client, $administrator);

        $this->assertSame(CredentialStatus::Revoked, $client->refresh()->status);
    }
}
