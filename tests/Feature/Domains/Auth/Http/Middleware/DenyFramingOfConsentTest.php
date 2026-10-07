<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Middleware;

use App\Domains\Auth\Http\Middleware\DenyFramingOfConsent;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\RunsAuthorizationCodeFlow;
use Tests\TestCase;

#[CoversClass(DenyFramingOfConsent::class)]
final class DenyFramingOfConsentTest extends TestCase
{
    use RunsAuthorizationCodeFlow;

    public function test_no_other_site_may_frame_the_consent_screen_or_its_answers(): void
    {
        $this->actingAs(User::factory()->create());
        $client = $this->registerApplication();

        [$consent] = $this->requestAuthorization($client);
        $consent->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");

        $this->delete('/oauth/authorize', ['client_id' => $client->getKey(), 'auth_token' => session('authToken')])
            ->assertRedirect()
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    // The page for a deleted or revoked application is rendered from an exception, inside this middleware.
    public function test_the_page_for_an_unknown_application_cannot_be_framed(): void
    {
        $this->actingAs(User::factory()->create());
        $client = $this->registerApplication();
        $client->delete();

        [$response] = $this->requestAuthorization($client);

        $response->assertBadRequest()->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_the_token_endpoint_is_left_alone(): void
    {
        $this->postJson('/oauth/token', ['grant_type' => 'client_credentials'])
            ->assertHeaderMissing('X-Frame-Options');
    }
}
