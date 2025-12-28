<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Controllers\Local;

use App\Domains\Auth\Http\Controllers\Local\ShowLoginCodeFormController;
use App\Domains\Auth\Models\LoginChallenge;
use App\Domains\Auth\ValueObjects\LoginCodeSession;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ShowLoginCodeFormController::class)]
class ShowLoginCodeFormControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.local.enabled' => true]);
    }

    public function test_redirects_to_request_when_no_email_in_session(): void
    {
        $response = $this->get(route('login-code.code'));

        $response->assertRedirect(route('login-code.request'));
    }

    public function test_clears_expired_challenge_id(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $expiredChallenge = LoginChallenge::create([
            'email' => $user->email,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->withSession([
            LoginCodeSession::EMAIL => $user->email,
            LoginCodeSession::CHALLENGE_ID => Crypt::encryptString((string) $expiredChallenge->id),
        ])->get(route('login-code.code'));

        $response->assertOk();
        $response->assertViewIs('auth.login-code');
        $response->assertSessionHas(LoginCodeSession::EMAIL, $user->email);
        $response->assertSessionMissing(LoginCodeSession::CHALLENGE_ID);
    }

    public function test_resend_available_at_is_zero_when_no_cooldown_active(): void
    {
        $email = 'test@example.com';
        $cooldownKey = "login-code-resend:{$email}";

        RateLimiter::clear($cooldownKey);

        $response = $this->withSession([
            LoginCodeSession::EMAIL => $email,
        ])->get(route('login-code.code'));

        $response->assertOk();
        $response->assertViewIs('auth.login-code');
        $response->assertViewHas('email', $email);
        $response->assertViewHas('resendAvailableAt', 0);
    }

    public function test_resend_available_at_is_future_timestamp_when_cooldown_active(): void
    {
        $email = 'test@example.com';
        $cooldownKey = "login-code-resend:{$email}";

        RateLimiter::hit($cooldownKey, 30);

        $response = $this->withSession([
            LoginCodeSession::EMAIL => $email,
        ])->get(route('login-code.code'));

        $response->assertOk();
        $response->assertViewIs('auth.login-code');

        $resendAvailableAt = $response->viewData('resendAvailableAt');

        $this->assertGreaterThan(0, $resendAvailableAt);
        $this->assertGreaterThan(now()->timestamp, $resendAvailableAt);
        $this->assertLessThanOrEqual(now()->addSeconds(30)->timestamp, $resendAvailableAt);
    }

    public function test_resend_available_at_computed_from_rate_limiter(): void
    {
        $email = 'test@example.com';
        $cooldownKey = "login-code-resend:{$email}";

        RateLimiter::hit($cooldownKey, 30);

        $response = $this->withSession([
            LoginCodeSession::EMAIL => $email,
        ])->get(route('login-code.code'));

        $response->assertOk();

        $resendAvailableAt = $response->viewData('resendAvailableAt');
        $expectedTimestamp = now()->addSeconds(RateLimiter::availableIn($cooldownKey))->timestamp;

        $this->assertEqualsWithDelta($expectedTimestamp, $resendAvailableAt, 1);
    }

    public function test_resend_available_at_cannot_be_manipulated_via_session(): void
    {
        $email = 'test@example.com';
        $cooldownKey = "login-code-resend:{$email}";

        RateLimiter::hit($cooldownKey, 30);

        $response = $this->withSession([
            LoginCodeSession::EMAIL => $email,
        ])->get(route('login-code.code'));

        $response->assertOk();

        $resendAvailableAt = $response->viewData('resendAvailableAt');

        $this->assertGreaterThan(0, $resendAvailableAt);
        $this->assertGreaterThan(now()->timestamp, $resendAvailableAt);
    }
}
