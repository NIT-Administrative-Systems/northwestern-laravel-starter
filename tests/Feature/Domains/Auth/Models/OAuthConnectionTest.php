<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Models;

use App\Domains\Auth\Models\OAuthConnection;
use App\Domains\User\Models\User;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Concerns\RunsAuthorizationCodeFlow;
use Tests\TestCase;

#[CoversNothing]
final class OAuthConnectionTest extends TestCase
{
    use RunsAuthorizationCodeFlow;

    // An application keeps access while it holds a refresh token, after its hour-long access token expires.
    public function test_a_connection_is_live_while_its_refresh_token_works(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $client = $this->registerApplication();
        [, $verifier] = $this->requestAuthorization($client);
        $this->exchange($client, $this->approve($client), $verifier);

        $this->assertTrue(OAuthConnection::query()->live()->exists());

        $this->travel(2)->hours();
        $this->assertTrue(OAuthConnection::query()->live()->exists());

        $this->travel(31)->days();
        $this->assertFalse(OAuthConnection::query()->live()->exists());
    }

    public function test_a_connection_whose_tokens_are_revoked_is_not_live(): void
    {
        $this->actingAs(User::factory()->create());
        $client = $this->registerApplication();
        [, $verifier] = $this->requestAuthorization($client);
        $this->exchange($client, $this->approve($client), $verifier);

        Passport::token()->newQuery()->update(['revoked' => true]);

        $this->assertFalse(OAuthConnection::query()->live()->exists());
    }

    public function test_first_connected_survives_authorizing_again(): void
    {
        $this->actingAs(User::factory()->create());
        $client = $this->registerApplication();
        [, $verifier] = $this->requestAuthorization($client);
        $this->exchange($client, $this->approve($client), $verifier);
        $connectedAt = OAuthConnection::query()->sole()->connected_at;

        // Its access token has expired by now, so Passport asks again.
        $this->travel(3)->days();
        [$response, $verifier] = $this->requestAuthorization($client);
        $response->assertOk();
        $this->exchange($client, $this->approve($client), $verifier)->assertOk();

        $this->assertTrue($connectedAt->eq(OAuthConnection::query()->sole()->connected_at));
    }
}
