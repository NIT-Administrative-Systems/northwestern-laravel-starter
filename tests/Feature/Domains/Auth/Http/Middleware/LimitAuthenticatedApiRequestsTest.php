<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Middleware;

use App\Domains\Auth\Http\Middleware\LimitAuthenticatedApiRequests;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\IssuesServiceClientTokens;
use Tests\TestCase;

#[CoversClass(LimitAuthenticatedApiRequests::class)]
final class LimitAuthenticatedApiRequestsTest extends TestCase
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
}
