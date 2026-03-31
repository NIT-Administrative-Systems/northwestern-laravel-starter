<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Controllers\Local;

use App\Domains\Auth\Actions\Local\IssueLoginChallenge;
use App\Domains\Auth\Http\Controllers\Local\SendLoginCodeController;
use App\Domains\Auth\Models\LoginChallenge;
use App\Domains\Auth\ValueObjects\LoginCodeSession;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Timebox;
use PHPUnit\Framework\Attributes\CoversClass;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(SendLoginCodeController::class)]
class SendLoginCodeControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('login-code.send')) {
            $this->markTestSkipped('Local auth routes are not registered.');
        }

        config(['local-auth.enabled' => true]);
        config(['local-auth.rate_limit_per_hour' => 5]);
        config(['local-auth.rate_limit_per_ip_per_hour' => 20]);
        config(['local-auth.code.digits' => 6]);
        config(['local-auth.code.resend_cooldown_seconds' => 30]);

        Mail::fake();

        $this->mock(Timebox::class, function ($mock) {
            $mock->shouldReceive('call')->andReturnUsing(
                fn (callable $callback, int $microseconds) => $callback(new Timebox())
            );
        });
    }

    public function test_send_creates_challenge_and_sets_session_for_existing_user(): void
    {
        User::factory()->affiliate()->create(['email' => 'test@example.com']);

        $response = $this->post(route('login-code.send'), [
            'email' => 'test@example.com',
        ]);

        $response->assertRedirect(route('login-code.code'));

        $response->assertSessionHas(LoginCodeSession::EMAIL, 'test@example.com');
        $response->assertSessionHas(LoginCodeSession::CHALLENGE_ID);

        $challengeIdEncrypted = session(LoginCodeSession::CHALLENGE_ID);
        $this->assertIsString($challengeIdEncrypted);
        $challenge = LoginChallenge::latest('id')->where('email', 'test@example.com')->first();
        $this->assertNotNull($challenge);
        $this->assertEquals($challenge->id, (int) Crypt::decryptString($challengeIdEncrypted));
    }

    public function test_send_sets_session_for_nonexistent_email(): void
    {
        $response = $this->post(route('login-code.send'), [
            'email' => 'missing@example.com',
        ]);

        $response->assertRedirect(route('login-code.code'));

        $this->assertEquals('missing@example.com', session(LoginCodeSession::EMAIL));
        $this->assertIsString(session(LoginCodeSession::CHALLENGE_ID));
    }

    public function test_send_validation_errors_for_invalid_email(): void
    {
        $response = $this->post(route('login-code.send'), ['email' => 'bad-email']);

        $response->assertSessionHasErrors('email');
    }

    public function test_send_requires_email(): void
    {
        $response = $this->post(route('login-code.send'), []);

        $response->assertSessionHasErrors('email');
    }

    public function test_send_returns_404_when_disabled(): void
    {
        config(['local-auth.enabled' => false]);

        $response = $this->post(route('login-code.send'), ['email' => 'test@example.com']);

        $response->assertNotFound();
    }

    public function test_send_returns_identical_response_for_existing_and_nonexisting_users(): void
    {
        User::factory()->affiliate()->create(['email' => 'existing@example.com']);

        $existingResponse = $this->post(route('login-code.send'), [
            'email' => 'existing@example.com',
        ]);

        $nonExistingResponse = $this->post(route('login-code.send'), [
            'email' => 'nonexisting@example.com',
        ]);

        $this->assertEquals(
            $existingResponse->getStatusCode(),
            $nonExistingResponse->getStatusCode(),
            'Status codes should be identical for existing and non-existing users'
        );

        $this->assertEquals(
            $existingResponse->headers->get('Location'),
            $nonExistingResponse->headers->get('Location'),
            'Redirect locations should be identical for existing and non-existing users'
        );

        $existingResponse->assertSessionHas(LoginCodeSession::EMAIL);
        $existingResponse->assertSessionHas(LoginCodeSession::CHALLENGE_ID);

        $nonExistingResponse->assertSessionHas(LoginCodeSession::EMAIL);
        $nonExistingResponse->assertSessionHas(LoginCodeSession::CHALLENGE_ID);
    }

    public function test_resend_redirects_to_request_when_email_missing(): void
    {
        $response = $this->post(route('login-code.resend'));

        $response->assertRedirect(route('login-code.request'));
    }

    public function test_resend_respects_server_side_cooldown(): void
    {
        $email = 'test@example.com';
        $cooldownKey = "login-code-resend:{$email}";

        RateLimiter::hit($cooldownKey, 30);

        $response = $this->withSession([
            LoginCodeSession::EMAIL => $email,
        ])->post(route('login-code.resend'));

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertStringContainsString('wait', session('status'));
    }

    public function test_resend_allows_request_when_cooldown_expired(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $cooldownKey = "login-code-resend:{$user->email}";

        RateLimiter::clear($cooldownKey);

        $response = $this->withSession([
            LoginCodeSession::EMAIL => $user->email,
        ])->post(route('login-code.resend'));

        $response->assertRedirect();
        $response->assertSessionHas(LoginCodeSession::CHALLENGE_ID);
        $response->assertSessionHas('status', 'Verification code resent.');
    }

    public function test_resend_successful_updates_session(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);

        RateLimiter::clear("login-code-resend:{$user->email}");

        $response = $this->withSession([
            LoginCodeSession::EMAIL => $user->email,
        ])->post(route('login-code.resend'));

        $response->assertRedirect();
        $response->assertSessionHas(LoginCodeSession::CHALLENGE_ID);
        $response->assertSessionHas('status', 'Verification code resent.');
    }

    public function test_resend_returns_identical_response_for_existing_and_nonexisting_users(): void
    {
        User::factory()->affiliate()->create(['email' => 'existing@example.com']);

        RateLimiter::clear('login-code-resend:existing@example.com');
        RateLimiter::clear('login-code-resend:nonexisting@example.com');

        $existingResponse = $this->withSession([
            LoginCodeSession::EMAIL => 'existing@example.com',
        ])->post(route('login-code.resend'));

        $nonExistingResponse = $this->withSession([
            LoginCodeSession::EMAIL => 'nonexisting@example.com',
        ])->post(route('login-code.resend'));

        $this->assertEquals(
            $existingResponse->getStatusCode(),
            $nonExistingResponse->getStatusCode(),
            'Status codes should be identical for existing and non-existing users'
        );

        $this->assertEquals(
            $existingResponse->headers->get('Location'),
            $nonExistingResponse->headers->get('Location'),
            'Redirect locations should be identical for existing and non-existing users'
        );
    }

    public function test_resend_nonexistent_user_receives_same_message_as_existing_user(): void
    {
        User::factory()->affiliate()->create(['email' => 'existing@example.com']);

        RateLimiter::clear('login-code-resend:nonexisting@example.com');

        $response = $this->withSession([
            LoginCodeSession::EMAIL => 'nonexisting@example.com',
        ])->post(route('login-code.resend'));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Verification code resent.');
    }

    public function test_resend_returns_404_when_disabled(): void
    {
        config(['local-auth.enabled' => false]);

        $response = $this->withSession([
            LoginCodeSession::EMAIL => 'test@example.com',
        ])->post(route('login-code.resend'));

        $response->assertNotFound();
    }

    public function test_send_returns_validation_error_when_challenge_issue_fails(): void
    {
        config(['local-auth.rate_limit_per_hour' => 1]);
        User::factory()->affiliate()->create(['email' => 'test@example.com']);

        RateLimiter::hit('login-code:test@example.com', 3600);

        $response = $this->post(route('login-code.send'), [
            'email' => 'test@example.com',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Too many login attempts',
            (string) session('errors')->first('email')
        );
    }

    public function test_send_rate_limits_nonexistent_email_identically_to_existing(): void
    {
        config(['local-auth.rate_limit_per_hour' => 1]);

        RateLimiter::hit('login-code:nonexistent@example.com', 3600);

        $response = $this->post(route('login-code.send'), [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Too many login attempts',
            (string) session('errors')->first('email')
        );
    }

    public function test_send_enforces_per_ip_rate_limit(): void
    {
        config(['local-auth.rate_limit_per_ip_per_hour' => 2]);

        $this->post(route('login-code.send'), ['email' => 'user1@example.com']);
        $this->post(route('login-code.send'), ['email' => 'user2@example.com']);

        $response = $this->post(route('login-code.send'), ['email' => 'user3@example.com']);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Too many login attempts',
            (string) session('errors')->first('email')
        );
    }

    public function test_per_ip_rate_limit_does_not_block_different_ips(): void
    {
        config(['local-auth.rate_limit_per_ip_per_hour' => 1]);

        // Exhaust rate limit for 127.0.0.1
        $this->post(route('login-code.send'), ['email' => 'user1@example.com']);

        // Request from a different IP should succeed
        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.100'])
            ->post(route('login-code.send'), ['email' => 'user2@example.com']);

        $response->assertRedirect(route('login-code.code'));
        $response->assertSessionHasNoErrors();
    }

    public function test_send_surfaces_runtime_exception_from_challenge_issuer_as_validation_error(): void
    {
        User::factory()->affiliate()->create(['email' => 'test@example.com']);

        $this->mock(IssueLoginChallenge::class, function ($mock) {
            $mock->shouldReceive('__invoke')
                ->once()
                ->andThrow(new RuntimeException('Too many login attempts. Please try again in 1 minute(s).'));
        });

        $response = $this->post(route('login-code.send'), [
            'email' => 'test@example.com',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Too many login attempts',
            (string) session('errors')->first('email')
        );
    }
}
