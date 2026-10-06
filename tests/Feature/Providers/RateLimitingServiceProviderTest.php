<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class RateLimitingServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['api.enabled' => true]);
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
        // Passport's endpoints keep plain JSON errors; Problem Details are for the REST API.
        $this->postJson('/oauth/token', ['grant_type' => 'client_credentials'])
            ->assertTooManyRequests()
            ->assertHeader('Content-Type', 'application/json');
    }
}
