<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Controllers\Local;

use App\Domains\Auth\Http\Controllers\Local\SendLoginCodeController;
use App\Domains\Auth\Models\LoginChallenge;
use App\Domains\Auth\ValueObjects\LoginCodeSession;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\Crypt;
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

        $challengeIdEncrypted = session(LoginCodeSession::CHALLENGE_ID);
        $this->assertIsString($challengeIdEncrypted);
        $challenge = LoginChallenge::latest('id')->where('email', 'test@example.com')->first();
        $this->assertNotNull($challenge);
        $this->assertEquals($challenge->id, (int) Crypt::decryptString($challengeIdEncrypted));
    }

    public function test_sends_code_for_nonexistent_email_without_session(): void
    {
        $response = $this->post(route('login-code.send'), [
            'email' => 'missing@example.com',
        ]);

        $response->assertRedirect(route('login-code.code'));

        $this->assertEquals('missing@example.com', session(LoginCodeSession::EMAIL));
        $this->assertIsString(session(LoginCodeSession::CHALLENGE_ID));
        $this->assertIsInt(session(LoginCodeSession::RESEND_AVAILABLE_AT));
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
