<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * The JSON health endpoint in routes/api.php, behind Chassis's RequireSecretToken: closed until
 * HEALTH_SECRET_TOKEN is set, then open only to requests that send it.
 */
#[CoversNothing]
final class HealthEndpointTest extends TestCase
{
    // .env.example ships HEALTH_SECRET_TOKEN empty, and Spatie's middleware treats that as "no check".
    public function test_the_endpoint_is_closed_until_a_token_is_set(): void
    {
        config(['health.secret_token' => null]);

        $this->getJson('/api/health')->assertForbidden();
        $this->getJson('/api/health', ['X-Secret-Token' => ''])->assertForbidden();
    }

    public function test_a_wrong_or_missing_token_is_refused(): void
    {
        config(['health.secret_token' => 'expected-token']);

        $this->getJson('/api/health')->assertForbidden();
        $this->getJson('/api/health', ['X-Secret-Token' => 'wrong-token'])->assertForbidden();
    }

    public function test_the_right_token_reaches_the_health_results(): void
    {
        config(['health.secret_token' => 'expected-token']);

        $response = $this->getJson('/api/health', ['X-Secret-Token' => 'expected-token']);

        // Spatie's controller answers 200 or 503 depending on the stored results; either way it sends no-store.
        $this->assertContains($response->status(), [200, 503]);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
