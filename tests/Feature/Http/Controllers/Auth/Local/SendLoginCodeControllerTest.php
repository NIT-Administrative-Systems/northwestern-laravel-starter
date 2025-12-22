<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth\Local;

use App\Domains\Core\ValueObjects\LoginCodeSession;
use App\Domains\User\Models\LoginChallenge;
use App\Domains\User\Models\User;
use App\Http\Controllers\Auth\Local\SendLoginCodeController;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SendLoginCodeController::class)]
class SendLoginCodeControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.local.enabled' => true]);
        config(['auth.local.rate_limit_per_hour' => 5]);
        config(['auth.local.code.digits' => 6]);

        Mail::fake();
    }

    public function test_sends_code_and_sets_session_for_existing_user(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);

        $response = $this->post(route('login-code.send'), [
            'email' => 'test@example.com',
        ]);

        $response->assertRedirect(route('login-code.code'));

        $response->assertSessionHas(LoginCodeSession::EMAIL, 'test@example.com');
        $response->assertSessionHas(LoginCodeSession::CHALLENGE_ID);
        $response->assertSessionHas(LoginCodeSession::RESEND_AVAILABLE_AT);

        $challengeId = session(LoginCodeSession::CHALLENGE_ID);
        $this->assertIsString($challengeId);
        $this->assertNotNull(LoginChallenge::find($challengeId));
    }

    public function test_sends_code_for_nonexistent_email_without_session(): void
    {
        $response = $this->post(route('login-code.send'), [
            'email' => 'missing@example.com',
        ]);

        $response->assertRedirect(route('login-code.code'));

        $response->assertSessionMissing(LoginCodeSession::EMAIL);
        $response->assertSessionMissing(LoginCodeSession::CHALLENGE_ID);
        $response->assertSessionMissing(LoginCodeSession::RESEND_AVAILABLE_AT);
    }

    public function test_validation_errors_for_invalid_email(): void
    {
        $response = $this->post(route('login-code.send'), ['email' => 'bad-email']);

        $response->assertSessionHasErrors('email');
    }

    public function test_requires_email(): void
    {
        $response = $this->post(route('login-code.send'), []);

        $response->assertSessionHasErrors('email');
    }

    public function test_returns_404_when_disabled(): void
    {
        config(['auth.local.enabled' => false]);

        $response = $this->post(route('login-code.send'), ['email' => 'test@example.com']);

        $response->assertNotFound();
    }
}
