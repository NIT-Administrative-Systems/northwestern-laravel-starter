<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Mail;

use App\Domains\User\Mail\LoginVerificationCodeNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LoginVerificationCodeNotification::class)]
class LoginVerificationCodeNotificationTest extends TestCase
{
    public function test_envelope_has_correct_subject(): void
    {
        config(['app.name' => 'Test App']);
        $mailable = new LoginVerificationCodeNotification(
            Crypt::encryptString('123456'),
            CarbonImmutable::now()->addMinutes(10)
        );

        $this->assertEquals('Sign in to Test App', $mailable->envelope()->subject);
    }

    public function test_content_includes_code_and_expiration(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2024-01-01 12:00:00'));
        $expiresAt = CarbonImmutable::now()->addMinutes(12);
        $expectedMinutes = 12;

        $mailable = new LoginVerificationCodeNotification(
            Crypt::encryptString('654321'),
            $expiresAt
        );

        $content = $mailable->content();

        $this->assertEquals('mail.auth.login-code', $content->markdown);
        $this->assertEquals('654321', $content->with['code']);
        $this->assertEquals($expectedMinutes, $content->with['expiresInMinutes']);
    }
}
