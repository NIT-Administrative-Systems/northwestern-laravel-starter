<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Domains\User\Enums\UserSegmentEnum;
use App\Domains\User\Models\LoginChallenge;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginRecord;
use App\Http\Controllers\Auth\LoginCodeController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LoginCodeController::class)]
class LoginCodeControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.local.enabled' => true]);
        config(['auth.local.rate_limit_per_hour' => 5]);
        config(['auth.local.code.digits' => 6]);

        Mail::fake();
    }

    public function test_show_request_form_displays_view(): void
    {
        $response = $this->get(route('login-code.request'));

        $response->assertOk();
        $response->assertViewIs('auth.login-code-request');
    }

    public function test_show_request_form_404_when_disabled(): void
    {
        config(['auth.local.enabled' => false]);

        $response = $this->get(route('login-code.request'));

        $response->assertNotFound();
    }

    public function test_send_code_redirects_to_code_form(): void
    {
        User::factory()->affiliate()->create(['email' => 'test@example.com']);

        $response = $this->post(route('login-code.send'), [
            'email' => 'test@example.com',
        ]);

        $response->assertRedirect(route('login-code.code'));
    }

    public function test_send_code_creates_challenge_for_existing_user(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);

        $response = $this->post(route('login-code.send'), [
            'email' => 'test@example.com',
        ]);

        $response->assertRedirect(route('login-code.code'));

        $response->assertSessionHas(LoginCodeController::SESSION_EMAIL, 'test@example.com');
        $response->assertSessionHas(LoginCodeController::SESSION_CHALLENGE_ID);
        $response->assertSessionHas(LoginCodeController::SESSION_RESEND_AVAILABLE_AT);

        $challengeId = session(LoginCodeController::SESSION_CHALLENGE_ID);
        $this->assertIsString($challengeId);
        $this->assertNotNull(LoginChallenge::find($challengeId));

        $resendAt = session(LoginCodeController::SESSION_RESEND_AVAILABLE_AT);
        $this->assertIsInt($resendAt);
        $this->assertGreaterThan(time(), $resendAt);
    }

    public function test_send_code_returns_same_message_for_nonexistent_email(): void
    {
        $response = $this->post(route('login-code.send'), [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertRedirect(route('login-code.code'));

        $response->assertSessionMissing(LoginCodeController::SESSION_EMAIL);
        $response->assertSessionMissing(LoginCodeController::SESSION_CHALLENGE_ID);
        $response->assertSessionMissing(LoginCodeController::SESSION_RESEND_AVAILABLE_AT);
    }

    public function test_show_code_form_clears_stale_challenge_id_when_expired(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);

        $expiredChallenge = LoginChallenge::create([
            'email' => $user->email,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->withSession([
            LoginCodeController::SESSION_EMAIL => $user->email,
            LoginCodeController::SESSION_CHALLENGE_ID => (string) $expiredChallenge->id,
            LoginCodeController::SESSION_RESEND_AVAILABLE_AT => now()->addSeconds(30)->timestamp,
        ])->get(route('login-code.code'));

        $response->assertOk();
        $response->assertSessionHas(LoginCodeController::SESSION_EMAIL, $user->email);
        $response->assertSessionMissing(LoginCodeController::SESSION_CHALLENGE_ID);
    }

    public function test_verify_clears_login_code_session_keys_on_success(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $code = '123456';

        $challenge = LoginChallenge::create([
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->withSession([
            LoginCodeController::SESSION_EMAIL => $user->email,
            LoginCodeController::SESSION_CHALLENGE_ID => (string) $challenge->id,
            LoginCodeController::SESSION_RESEND_AVAILABLE_AT => now()->addSeconds(30)->timestamp,
        ])->post(route('login-code.verify'), [
            'code' => $code,
        ]);

        $response->assertRedirect('/');

        $response->assertSessionMissing(LoginCodeController::SESSION_EMAIL);
        $response->assertSessionMissing(LoginCodeController::SESSION_CHALLENGE_ID);
        $response->assertSessionMissing(LoginCodeController::SESSION_RESEND_AVAILABLE_AT);

        $this->assertAuthenticatedAs($user);
    }

    public function test_send_code_validates_email_format(): void
    {
        $response = $this->post(route('login-code.send'), [
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_send_code_requires_email(): void
    {
        $response = $this->post(route('login-code.send'), []);

        $response->assertSessionHasErrors('email');
    }

    public function test_send_code_404_when_disabled(): void
    {
        config(['auth.local.enabled' => false]);

        $response = $this->post(route('login-code.send'), [
            'email' => 'test@example.com',
        ]);

        $response->assertNotFound();
    }

    public function test_show_code_form_requires_email_session(): void
    {
        $response = $this->get(route('login-code.code'));

        $response->assertRedirect(route('login-code.request'));
    }

    public function test_verify_authenticates_user_with_valid_code(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $code = '123456';

        $challenge = LoginChallenge::create([
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        session([
            'login_code.email' => $user->email,
            'login_code.challenge_id' => (string) $challenge->id,
        ]);

        $response = $this->post(route('login-code.verify'), [
            'code' => $code,
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_verify_redirects_to_intended_url(): void
    {
        $user = User::factory()->affiliate()->create();
        $code = '123456';

        $challenge = LoginChallenge::create([
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        session([
            'login_code.email' => $user->email,
            'login_code.challenge_id' => (string) $challenge->id,
        ]);

        session()->put('url.intended', '/dashboard');

        $response = $this->post(route('login-code.verify'), [
            'code' => $code,
        ]);

        $response->assertRedirect('/dashboard');
    }

    public function test_verify_marks_challenge_as_consumed(): void
    {
        $user = User::factory()->affiliate()->create();
        $code = '123456';

        $challenge = LoginChallenge::create([
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        session([
            'login_code.email' => $user->email,
            'login_code.challenge_id' => (string) $challenge->id,
        ]);

        $this->post(route('login-code.verify'), ['code' => $code]);

        $this->assertNotNull($challenge->fresh()->consumed_at);
    }

    public function test_verify_creates_login_record(): void
    {
        $user = User::factory()->affiliate()->create();
        $code = '123456';

        $challenge = LoginChallenge::create([
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        session([
            'login_code.email' => $user->email,
            'login_code.challenge_id' => (string) $challenge->id,
        ]);

        $this->assertEquals(0, UserLoginRecord::count());

        $this->post(route('login-code.verify'), ['code' => $code]);

        $this->assertEquals(1, UserLoginRecord::count());
        $loginRecord = UserLoginRecord::first();
        $this->assertEquals($user->id, $loginRecord->user_id);
        $this->assertEquals(UserSegmentEnum::EXTERNAL_USER, $loginRecord->segment);
    }

    public function test_verify_returns_error_for_invalid_code(): void
    {
        $user = User::factory()->affiliate()->create();
        $challenge = LoginChallenge::create([
            'email' => $user->email,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        session([
            'login_code.email' => $user->email,
            'login_code.challenge_id' => (string) $challenge->id,
        ]);

        $response = $this->post(route('login-code.verify'), ['code' => '000000']);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_verify_404_when_disabled(): void
    {
        config(['auth.local.enabled' => false]);

        $response = $this->post(route('login-code.verify'), ['code' => '123456']);

        $response->assertNotFound();
    }
}
