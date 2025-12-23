<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Controllers\Local;

use App\Domains\Auth\Http\Controllers\Local\ResendLoginCodeController;
use App\Domains\Auth\ValueObjects\LoginCodeSession;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ResendLoginCodeController::class)]
class ResendLoginCodeControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.local.enabled' => true]);
        Mail::fake();
    }

    public function test_redirects_to_request_when_email_missing(): void
    {
        $response = $this->post(route('login-code.resend'));

        $response->assertRedirect(route('login-code.request'));
    }

    public function test_respects_cooldown(): void
    {
        $response = $this->withSession([
            LoginCodeSession::EMAIL => 'test@example.com',
            LoginCodeSession::RESEND_AVAILABLE_AT => now()->addMinutes(5)->timestamp,
        ])->post(route('login-code.resend'));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Please wait before requesting another code.');
    }

    public function test_sets_resend_timestamp_for_missing_user(): void
    {
        $response = $this->withSession([
            LoginCodeSession::EMAIL => 'missing@example.com',
            LoginCodeSession::RESEND_AVAILABLE_AT => 0,
        ])->post(route('login-code.resend'));

        $response->assertRedirect();
        $response->assertSessionHas(LoginCodeSession::RESEND_AVAILABLE_AT);
    }

    public function test_successful_resend_updates_session(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $response = $this->withSession([
            LoginCodeSession::EMAIL => $user->email,
            LoginCodeSession::RESEND_AVAILABLE_AT => 0,
        ])->post(route('login-code.resend'));

        $response->assertRedirect();
        $response->assertSessionHas(LoginCodeSession::CHALLENGE_ID);
        $response->assertSessionHas(LoginCodeSession::RESEND_AVAILABLE_AT);
        $response->assertSessionHas('status', 'Verification code resent.');
    }
}
