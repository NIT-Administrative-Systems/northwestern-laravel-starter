<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\OAuth;

use App\Domains\Auth\Actions\Applications\DisconnectApplication;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Http\Controllers\SwitchOAuthAccountController;
use App\Domains\Auth\Http\Middleware\RefuseOAuthConsentWhileImpersonating;
use App\Domains\Auth\Listeners\RecordOAuthConnection;
use App\Domains\Auth\Models\OAuthConnection;
use App\Domains\Auth\Notifications\ApplicationConnectedNotification;
use App\Domains\User\Models\User;
use App\Providers\OAuthServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Events\AccessTokenCreated;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\IssuesPersonalAccessTokens;
use Tests\Concerns\IssuesServiceClientTokens;
use Tests\Concerns\RunsAuthorizationCodeFlow;
use Tests\TestCase;

/**
 * The whole authorization code flow with PKCE, through Passport's real endpoints.
 */
#[CoversClass(OAuthServiceProvider::class)]
#[CoversClass(RecordOAuthConnection::class)]
#[CoversClass(RefuseOAuthConsentWhileImpersonating::class)]
#[CoversClass(SwitchOAuthAccountController::class)]
final class AuthorizationCodeFlowTest extends TestCase
{
    use IssuesPersonalAccessTokens, IssuesServiceClientTokens, RunsAuthorizationCodeFlow;

    public function test_a_person_connects_an_application_which_then_calls_the_api_as_them(): void
    {
        Notification::fake();
        $user = User::factory()->create(['first_name' => 'Willie']);
        $client = $this->registerApplication();
        $this->actingAs($user);

        [$consent, $verifier] = $this->requestAuthorization($client);
        $consent->assertOk()
            ->assertSee('Connect Reporting Tool')
            ->assertSee('View everyone\'s profiles and details.')
            ->assertSee('localhost')
            ->assertSee('Switch Account');

        $tokens = $this->exchange($client, $this->approve($client), $verifier)->assertOk();

        $this->withToken($tokens->json('access_token'))->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.username', $user->username);

        $connection = OAuthConnection::query()->sole();
        $this->assertSame(['view-users'], $connection->scopes);
        Notification::assertSentTo($user, ApplicationConnectedNotification::class);
    }

    public function test_a_refresh_token_gets_a_new_access_token_without_a_new_connection_alert(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->create());
        $client = $this->registerApplication();
        [, $verifier] = $this->requestAuthorization($client);
        $tokens = $this->exchange($client, $this->approve($client), $verifier)->json();

        $refreshed = $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client->getKey(),
            'refresh_token' => $tokens['refresh_token'],
        ])->assertOk();

        $this->withToken($refreshed->json('access_token'))->getJson('/api/v1/me')->assertOk();
        Notification::assertSentTimes(ApplicationConnectedNotification::class, 1);
    }

    public function test_a_wrong_code_verifier_is_refused(): void
    {
        $this->actingAs(User::factory()->create());
        $client = $this->registerApplication();
        [, $verifier] = $this->requestAuthorization($client);

        $this->exchange($client, $this->approve($client), $verifier . 'x')->assertBadRequest();
    }

    public function test_denying_returns_to_the_application_with_an_error(): void
    {
        $this->actingAs(User::factory()->create());
        $client = $this->registerApplication();
        $this->requestAuthorization($client);

        $this->delete('/oauth/authorize', ['state' => 'state-123', 'client_id' => $client->getKey(), 'auth_token' => session('authToken')])
            ->assertRedirectContains(self::REDIRECT_URI)
            ->assertRedirectContains('error=access_denied');

        $this->assertSame(0, OAuthConnection::query()->count());
    }

    // After disconnecting, nothing the application holds works: not its access token, not its refresh token.
    public function test_disconnecting_stops_the_access_and_refresh_tokens(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $client = $this->registerApplication();
        [, $verifier] = $this->requestAuthorization($client);
        $tokens = $this->exchange($client, $this->approve($client), $verifier)->json();

        resolve(DisconnectApplication::class)(OAuthConnection::query()->sole(), $user);

        $this->withToken($tokens['access_token'])->getJson('/api/v1/me')->assertUnauthorized();
        $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $client->getKey(), 'refresh_token' => $tokens['refresh_token']])->assertBadRequest();
        $this->assertSame(0, OAuthConnection::query()->count());
    }

    public function test_a_first_party_application_skips_the_consent_screen(): void
    {
        $this->actingAs(User::factory()->create());
        $client = $this->registerApplication(firstParty: true);

        [$response, $verifier] = $this->requestAuthorization($client);

        $this->exchange($client, $this->codeFrom($response), $verifier)->assertOk();
    }

    // Environment lockdown covers the consent screen, so a locked-out person can't connect an application.
    public function test_the_consent_screen_follows_environment_lockdown(): void
    {
        config(['platform.lockdown.enabled' => true]);
        $this->actingAs(User::factory()->create());
        $client = $this->registerApplication();

        [$response] = $this->requestAuthorization($client);

        $response->assertRedirect(route('filament.app.environment-lockdown'));
        $this->assertSame(0, OAuthConnection::query()->count());
    }

    public function test_an_application_only_gets_the_scopes_it_is_allowed(): void
    {
        $this->actingAs(User::factory()->create());
        $client = $this->registerApplication(scopes: []);

        [$consent, $verifier] = $this->requestAuthorization($client);
        $consent->assertDontSee(SystemPermission::ViewUsers->description());

        $tokens = $this->exchange($client, $this->approve($client), $verifier)->assertOk();
        $this->assertSame([], OAuthConnection::query()->sole()->scopes);
        $this->assertNotEmpty($tokens->json('access_token'));
    }

    public function test_a_signed_out_person_signs_in_first_and_returns_to_the_consent_screen(): void
    {
        $client = $this->registerApplication();

        [$response] = $this->requestAuthorization($client);

        $response->assertRedirect(route('filament.app.auth.login'));
        $this->assertStringStartsWith(url('/oauth/authorize?'), (string) session('url.intended'));
    }

    public function test_consent_is_refused_while_impersonating(): void
    {
        $this->actingAs(User::factory()->create());
        $client = $this->registerApplication(firstParty: true);
        $impersonate = Mockery::mock();
        $impersonate->shouldReceive('isImpersonating')->andReturn(true);
        $impersonate->shouldReceive('getImpersonatorId')->andReturn(null);
        $this->app->instance('impersonate', $impersonate);

        [$response] = $this->requestAuthorization($client);

        $response->assertForbidden();
        $this->assertSame(0, OAuthConnection::query()->count());
    }

    // A consent screen approved after its request expired has nowhere to return to.
    public function test_an_expired_consent_request_shows_the_page_expired_error(): void
    {
        $this->actingAs(User::factory()->create());
        $client = $this->registerApplication();

        $this->post('/oauth/authorize', ['state' => 'state-123', 'client_id' => $client->getKey(), 'auth_token' => 'stale'])
            ->assertStatus(419)
            ->assertSee('Page Expired');
    }

    public function test_switch_account_signs_out_and_returns_to_the_consent_screen_after_sign_in(): void
    {
        $this->actingAs(User::factory()->create()->fresh());
        $authorizeUrl = url('/oauth/authorize?client_id=abc&response_type=code');

        $this->post(route('oauth.switch-account'), ['return_to' => $authorizeUrl])
            ->assertRedirect(route('filament.app.auth.login'));

        $this->assertGuest();
        $this->assertSame($authorizeUrl, session('url.intended'));
    }

    public function test_switch_account_never_returns_anywhere_but_the_consent_screen(): void
    {
        $this->actingAs(User::factory()->create()->fresh());

        $this->post(route('oauth.switch-account'), ['return_to' => 'https://evil.example.test/']);

        $this->assertNull(session('url.intended'));
    }

    // Only an application acting for a person is a connection: not a service client, not a person's own token.
    public function test_service_client_and_personal_tokens_record_no_connection(): void
    {
        $this->serviceClientToken(User::factory()->api()->create());
        $this->personalAccessToken(User::factory()->create());

        $this->assertSame(0, OAuthConnection::query()->count());
    }

    // Event discovery registers the listener; registering it again ran it twice for every token.
    public function test_the_connection_listener_is_registered_once(): void
    {
        $this->assertCount(1, Event::getRawListeners()[AccessTokenCreated::class]);
    }

    public function test_a_token_for_someone_who_no_longer_exists_records_no_connection(): void
    {
        $client = $this->registerApplication();

        resolve(RecordOAuthConnection::class)->handle(new AccessTokenCreated('missing-token', '999999', $client->getKey()));

        $this->assertSame(0, OAuthConnection::query()->count());
    }
}
