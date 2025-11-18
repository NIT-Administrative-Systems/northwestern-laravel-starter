<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Domains\User\Enums\UserSegmentEnum;
use App\Domains\User\Models\LoginLink;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginRecord;
use App\Http\Controllers\Auth\LoginLinkController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LoginLinkController::class)]
class LoginLinkControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.local.enabled' => true]);
        config(['auth.local.rate_limit_per_hour' => 5]);

        Mail::fake();
    }

    public function test_show_request_form_displays_view(): void
    {
        $response = $this->get(route('login-link.request'));

        $response->assertOk();
        $response->assertViewIs('auth.login-link-request');
    }

    public function test_show_request_form_404_when_disabled(): void
    {
        config(['auth.local.enabled' => false]);

        $response = $this->get(route('login-link.request'));

        $response->assertNotFound();
    }

    public function test_send_link_sends_to_valid_local_user(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);

        $response = $this->post(route('login-link.send'), [
            'email' => 'test@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'If an account with that email exists, a login link has been sent.');

        $this->assertEquals(1, $user->login_links()->count());
    }

    public function test_send_link_returns_same_message_for_nonexistent_email(): void
    {
        $response = $this->post(route('login-link.send'), [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'If an account with that email exists, a login link has been sent.');
    }

    public function test_send_link_normalizes_email_to_lowercase(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);

        $response = $this->post(route('login-link.send'), [
            'email' => 'TEST@EXAMPLE.COM',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertEquals(1, $user->login_links()->count());
    }

    public function test_send_link_validates_email_format(): void
    {
        $response = $this->post(route('login-link.send'), [
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_send_link_requires_email(): void
    {
        $response = $this->post(route('login-link.send'), []);

        $response->assertSessionHasErrors('email');
    }

    public function test_send_link_handles_runtime_exception_from_send_action(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'ratelimitexc@example.com']);

        config(['auth.local.rate_limit_per_hour' => 1]);
        $this->post(route('login-link.send'), ['email' => 'ratelimitexc@example.com']);

        $response = $this->post(route('login-link.send'), [
            'email' => 'ratelimitexc@example.com',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_send_link_404_when_disabled(): void
    {
        config(['auth.local.enabled' => false]);

        $response = $this->post(route('login-link.send'), [
            'email' => 'test@example.com',
        ]);

        $response->assertNotFound();
    }

    public function test_verify_authenticates_user_with_valid_token(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $rawToken = Str::random(64);

        LoginLink::create([
            'user_id' => $user->id,
            'token' => LoginLink::hashFromPlain($rawToken),
            'email' => $user->email,
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->get(route('login-link.verify', ['token' => $rawToken]));

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_verify_redirects_to_intended_url(): void
    {
        $user = User::factory()->affiliate()->create();
        $rawToken = Str::random(64);

        LoginLink::create([
            'user_id' => $user->id,
            'token' => LoginLink::hashFromPlain($rawToken),
            'email' => $user->email,
            'expires_at' => now()->addMinutes(15),
        ]);

        session()->put('url.intended', '/dashboard');

        $response = $this->get(route('login-link.verify', ['token' => $rawToken]));

        $response->assertRedirect('/dashboard');
    }

    public function test_verify_marks_link_as_used(): void
    {
        $user = User::factory()->affiliate()->create();
        $rawToken = Str::random(64);

        $loginLink = LoginLink::create([
            'user_id' => $user->id,
            'token' => LoginLink::hashFromPlain($rawToken),
            'email' => $user->email,
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->assertNull($loginLink->used_at);

        $this->get(route('login-link.verify', ['token' => $rawToken]));

        $this->assertNotNull($loginLink->fresh()->used_at);
    }

    public function test_verify_stores_ip_address_on_login_link(): void
    {
        $user = User::factory()->affiliate()->create();
        $rawToken = Str::random(64);

        $loginLink = LoginLink::create([
            'user_id' => $user->id,
            'token' => LoginLink::hashFromPlain($rawToken),
            'email' => $user->email,
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->get(route('login-link.verify', ['token' => $rawToken]));

        $this->assertNotNull($loginLink->fresh()->used_ip_address);
    }

    public function test_verify_redirects_with_error_for_invalid_token(): void
    {
        $response = $this->get(route('login-link.verify', ['token' => 'invalid-token']));

        $response->assertRedirect(route('login-link.request'));
        $response->assertSessionHas('status-danger', 'This login link is invalid or has expired. Please request a new one.');
        $this->assertGuest();
    }

    public function test_verify_verifies_email_on_first_login(): void
    {
        $user = User::factory()->affiliate()->create([
            'email' => 'test@example.com',
            'email_verified_at' => null,
        ]);
        $rawToken = Str::random(64);

        LoginLink::create([
            'user_id' => $user->id,
            'token' => LoginLink::hashFromPlain($rawToken),
            'email' => $user->email,
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->assertNull($user->email_verified_at);

        $this->get(route('login-link.verify', ['token' => $rawToken]));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verify_does_not_overwrite_existing_email_verification(): void
    {
        $verifiedAt = now()->subDays(30);
        $user = User::factory()->affiliate()->create([
            'email' => 'test@example.com',
        ]);

        // Manually set email_verified_at after creation
        $user->update(['email_verified_at' => $verifiedAt]);
        $user = $user->fresh();

        $rawToken = Str::random(64);

        LoginLink::create([
            'user_id' => $user->id,
            'token' => LoginLink::hashFromPlain($rawToken),
            'email' => $user->email,
            'expires_at' => now()->addMinutes(15),
        ]);

        $originalVerifiedAt = $user->email_verified_at;

        $this->get(route('login-link.verify', ['token' => $rawToken]));

        // Should remain the same (not be updated)
        $freshUser = $user->fresh();
        $this->assertNotNull($freshUser->email_verified_at);
        $this->assertEquals($originalVerifiedAt, $freshUser->email_verified_at);
    }

    public function test_verify_creates_login_record(): void
    {
        $user = User::factory()->affiliate()->create();
        $rawToken = Str::random(64);

        LoginLink::create([
            'user_id' => $user->id,
            'token' => LoginLink::hashFromPlain($rawToken),
            'email' => $user->email,
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->assertEquals(0, UserLoginRecord::count());

        $this->get(route('login-link.verify', ['token' => $rawToken]));

        $this->assertEquals(1, UserLoginRecord::count());
        $loginRecord = UserLoginRecord::first();
        $this->assertEquals($user->id, $loginRecord->user_id);
        $this->assertEquals(UserSegmentEnum::EXTERNAL_USER, $loginRecord->segment);
    }

    public function test_verify_regenerates_session(): void
    {
        $user = User::factory()->affiliate()->create();
        $rawToken = Str::random(64);

        LoginLink::create([
            'user_id' => $user->id,
            'token' => LoginLink::hashFromPlain($rawToken),
            'email' => $user->email,
            'expires_at' => now()->addMinutes(15),
        ]);

        $oldSessionId = session()->getId();

        $this->get(route('login-link.verify', ['token' => $rawToken]));

        $newSessionId = session()->getId();

        $this->assertNotEquals($oldSessionId, $newSessionId);
    }

    public function test_verify_sets_remember_cookie(): void
    {
        $user = User::factory()->affiliate()->create();
        $rawToken = Str::random(64);

        LoginLink::create([
            'user_id' => $user->id,
            'token' => LoginLink::hashFromPlain($rawToken),
            'email' => $user->email,
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->get(route('login-link.verify', ['token' => $rawToken]));

        // Check that remember token was set on user
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_verify_404_when_disabled(): void
    {
        config(['auth.local.enabled' => false]);

        $response = $this->get(route('login-link.verify', ['token' => 'some-token']));

        $response->assertNotFound();
    }

    public function test_send_link_handles_whitespace_in_email(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);

        $response = $this->post(route('login-link.send'), [
            'email' => '  test@example.com  ',
        ]);

        $response->assertRedirect();
    }

    public function test_verify_invalid_token_returns_error_not_exception(): void
    {
        // When ValidateLoginLink returns null, the controller redirects with error
        // rather than throwing exception
        $response = $this->get(route('login-link.verify', ['token' => 'totally-invalid-nonexistent-token']));

        $response->assertRedirect(route('login-link.request'));
        $response->assertSessionHas('status-danger');
        $this->assertGuest();
    }
}
