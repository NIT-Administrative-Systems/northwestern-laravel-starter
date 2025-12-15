<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Domains\User\Enums\AccessTokenStatusEnum;
use App\Domains\User\Models\AccessToken;
use App\Domains\User\Models\User;
use App\Http\Controllers\Api\V1\AccessTokenApiController;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\ApiTestCase;

#[CoversClass(AccessTokenApiController::class)]
class AccessTokenApiControllerTest extends ApiTestCase
{
    public function endpoint(): string
    {
        return '/api/v1/me/tokens';
    }

    public static function methods(): array
    {
        return ['get', 'post'];
    }

    public function test_requires_authentication(): void
    {
        $this->json('get', $this->endpoint())->assertUnauthorized();

        $this->json('post', $this->endpoint())->assertUnauthorized();
    }

    public function test_show_requires_authentication(): void
    {
        $token = AccessToken::factory()->for($this->apiUser)->create();

        $this->json('get', $this->endpoint() . '/' . $token->id)
            ->assertUnauthorized();
    }

    public function test_destroy_requires_authentication(): void
    {
        $token = AccessToken::factory()->for($this->apiUser)->create();

        $this->json('delete', $this->endpoint() . '/' . $token->id)
            ->assertUnauthorized();
    }

    public function test_index_returns_users_access_tokens(): void
    {
        $response = $this->authenticatedGet();

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'status',
                    'last_used_at',
                    'usage_count',
                    'expires_at',
                    'created_at',
                    'allowed_ips',
                ],
            ],
            'links',
            'meta',
        ]);
    }

    public function test_index_only_returns_authenticated_users_tokens(): void
    {
        $otherUser = User::factory()->api()->create();
        AccessToken::factory()->for($otherUser)->create(['name' => 'Other User Token']);

        $response = $this->authenticatedGet();

        $response->assertOk();

        $tokenNames = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertNotContains('Other User Token', $tokenNames);
    }

    public function test_index_returns_tokens_ordered_by_relevance(): void
    {
        $activeToken = AccessToken::factory()->for($this->apiUser)->create([
            'name' => 'Active Token',
            'last_used_at' => now()->subHour(),
        ]);

        $expiredToken = AccessToken::factory()->for($this->apiUser)->create([
            'name' => 'Expired Token',
            'expires_at' => now()->subDay(),
        ]);

        $revokedToken = AccessToken::factory()->for($this->apiUser)->create([
            'name' => 'Revoked Token',
            'revoked_at' => now()->subDay(),
        ]);

        $response = $this->authenticatedGet();

        $response->assertOk();

        $tokens = collect($response->json('data'));
        $firstToken = $tokens->first();

        $this->assertEquals(AccessTokenStatusEnum::ACTIVE->value, $firstToken['status']);
    }

    public function test_store_creates_new_access_token(): void
    {
        $response = $this->authenticatedPost([
            'name' => 'Test API Token',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas(AccessToken::class, [
            'user_id' => $this->apiUser->id,
            'name' => 'Test API Token',
        ]);
    }

    public function test_store_returns_bearer_token_in_meta(): void
    {
        $response = $this->authenticatedPost([
            'name' => 'Test API Token',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'status',
            ],
            'meta' => [
                'bearer_token',
                'message',
            ],
        ]);

        $bearerToken = $response->json('meta.bearer_token');
        $this->assertIsString($bearerToken);
        $this->assertNotEmpty($bearerToken);
    }

    public function test_store_bearer_token_can_authenticate(): void
    {
        $response = $this->authenticatedPost([
            'name' => 'Test API Token',
        ]);

        $bearerToken = $response->json('meta.bearer_token');

        $testResponse = $this->getJson('/api/v1/me', [
            'Authorization' => 'Bearer ' . $bearerToken,
        ]);

        $testResponse->assertOk();
    }

    public function test_store_with_expires_at_timestamp(): void
    {
        $expiresAt = now()->addDays(30);

        $response = $this->authenticatedPost([
            'name' => 'Expiring Token',
            'expires_at' => $expiresAt->timestamp,
        ]);

        $response->assertCreated();

        $token = AccessToken::find($response->json('data.id'));
        $this->assertNotNull($token->expires_at);
        $this->assertEquals($expiresAt->timestamp, $token->expires_at->timestamp);
    }

    public function test_store_without_expires_at_creates_non_expiring_token(): void
    {
        $response = $this->authenticatedPost([
            'name' => 'Non-Expiring Token',
        ]);

        $response->assertCreated();

        $token = AccessToken::find($response->json('data.id'));
        $this->assertNull($token->expires_at);
    }

    public function test_store_with_allowed_ips(): void
    {
        $allowedIps = ['192.168.1.1', '10.0.0.0/24'];

        $response = $this->authenticatedPost([
            'name' => 'IP Restricted Token',
            'allowed_ips' => $allowedIps,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.allowed_ips', $allowedIps);

        $token = AccessToken::find($response->json('data.id'));
        $this->assertEquals($allowedIps, $token->allowed_ips);
    }

    public function test_show_returns_token_details(): void
    {
        $token = AccessToken::factory()->for($this->apiUser)->create([
            'name' => 'Viewable Token',
        ]);

        $response = $this->authenticatedGet();
        $response = $this->getJson(
            $this->endpoint() . '/' . $token->id,
            $this->bearerAuthenticationHeader()
        );

        $response->assertOk();
        $response->assertJsonPath('data.id', $token->id);
        $response->assertJsonPath('data.name', 'Viewable Token');
    }

    public function test_show_returns_403_for_other_users_token(): void
    {
        $otherUser = User::factory()->api()->create();
        $otherToken = AccessToken::factory()->for($otherUser)->create();

        $response = $this->getJson(
            $this->endpoint() . '/' . $otherToken->id,
            $this->bearerAuthenticationHeader()
        );

        $response->assertForbidden();
        $response->assertJson([
            'detail' => 'This token does not belong to you.',
        ]);
    }

    public function test_show_returns_404_for_nonexistent_token(): void
    {
        $response = $this->getJson(
            $this->endpoint() . '/99999',
            $this->bearerAuthenticationHeader()
        );

        $response->assertNotFound();
    }

    public function test_destroy_revokes_token(): void
    {
        $token = AccessToken::factory()->for($this->apiUser)->create([
            'name' => 'Token to Revoke',
        ]);

        $response = $this->deleteJson(
            $this->endpoint() . '/' . $token->id,
            [],
            $this->bearerAuthenticationHeader()
        );

        $response->assertNoContent();

        $token->refresh();
        $this->assertNotNull($token->revoked_at);
        $this->assertEquals(AccessTokenStatusEnum::REVOKED, $token->status);
    }

    public function test_destroy_returns_403_for_other_users_token(): void
    {
        $otherUser = User::factory()->api()->create();
        $otherToken = AccessToken::factory()->for($otherUser)->create();

        $response = $this->deleteJson(
            $this->endpoint() . '/' . $otherToken->id,
            [],
            $this->bearerAuthenticationHeader()
        );

        $response->assertForbidden();
        $response->assertJson([
            'detail' => 'This token does not belong to you.',
        ]);
    }

    public function test_destroy_prevents_revoking_current_token(): void
    {
        $currentToken = $this->apiUser->access_tokens()->first();

        $response = $this->deleteJson(
            $this->endpoint() . '/' . $currentToken->id,
            [],
            $this->bearerAuthenticationHeader()
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token']);
        $response->assertJson([
            'errors' => [
                'token' => ['You cannot revoke the token you are currently using.'],
            ],
        ]);

        $currentToken->refresh();
        $this->assertNull($currentToken->revoked_at);
    }

    public function test_destroy_returns_404_for_nonexistent_token(): void
    {
        $response = $this->deleteJson(
            $this->endpoint() . '/99999',
            [],
            $this->bearerAuthenticationHeader()
        );

        $response->assertNotFound();
    }
}
