<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Domains\Auth\Http\Middleware\LimitAuthenticatedApiRequests;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\IssuesServiceClientTokens;
use Tests\TestCase;

#[CoversClass(LimitAuthenticatedApiRequests::class)]
final class RateLimitingServiceProviderTest extends TestCase
{
    use IssuesServiceClientTokens;

    protected function setUp(): void
    {
        parent::setUp();

        config(['api.enabled' => true]);
    }

    // Every integration behind one IP address (an API gateway, say) must not share one limit.
    public function test_authenticated_requests_are_limited_per_client_not_per_ip_address(): void
    {
        config(['rate-limiting.api.per_minute' => 1, 'rate-limiting.api.per_ip_per_minute' => 100]);

        [$tokenA] = $this->serviceClientToken(User::factory()->api()->create());
        [$tokenB] = $this->serviceClientToken(User::factory()->api()->create());

        $this->withToken($tokenA)->getJson('/api/v1/me')->assertOk();
        $this->withToken($tokenA)->getJson('/api/v1/me')
            ->assertTooManyRequests()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertHeader('Retry-After');
        $this->withToken($tokenB)->getJson('/api/v1/me')->assertOk();
    }

    public function test_requests_are_limited_per_ip_address_before_authentication(): void
    {
        config(['rate-limiting.api.per_ip_per_minute' => 1]);

        $this->withToken('unknown')->getJson('/api/v1/me')->assertUnauthorized();
        $this->withToken('another-unknown')->getJson('/api/v1/me')->assertTooManyRequests();
    }

    public function test_the_oauth_token_endpoint_shares_the_per_ip_limit(): void
    {
        config(['rate-limiting.api.per_ip_per_minute' => 1]);

        $this->postJson('/oauth/token', ['grant_type' => 'client_credentials'])->assertBadRequest();
        $this->postJson('/oauth/token', ['grant_type' => 'client_credentials'])->assertTooManyRequests();
    }
}
