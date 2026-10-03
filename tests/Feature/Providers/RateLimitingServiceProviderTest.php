<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Domains\Auth\Models\AccessToken;
use App\Domains\User\Models\User;
use App\Providers\RateLimitingServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RateLimitingServiceProvider::class)]
final class RateLimitingServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['api.enabled' => true, 'rate-limiting.api.per_minute' => 1]);
    }

    // The limiter runs before the token middleware, so it must find the user itself; otherwise
    // every integration behind one IP address shares a single limit.
    public function test_the_api_limit_is_per_api_user_not_per_ip_address(): void
    {
        $this->apiUserWithToken('token-a');
        $this->apiUserWithToken('token-b');

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer token-a'])->assertOk();
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer token-a'])
            ->assertTooManyRequests()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertHeader('Retry-After');
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer token-b'])->assertOk();
    }

    public function test_requests_without_a_valid_token_are_limited_by_ip_address(): void
    {
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer unknown'])->assertUnauthorized();
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer another-unknown'])->assertTooManyRequests();
    }

    private function apiUserWithToken(string $plainToken): User
    {
        return User::factory()
            ->api()
            ->has(AccessToken::factory()->state([
                'token_hash' => AccessToken::hashFromPlain($plainToken),
                'expires_at' => null,
            ]), 'access_tokens')
            ->createOne();
    }
}
