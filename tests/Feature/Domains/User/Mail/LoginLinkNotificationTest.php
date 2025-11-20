<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Mail;

use App\Domains\User\Mail\LoginLinkNotification;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LoginLinkNotification::class)]
class LoginLinkNotificationTest extends TestCase
{
    public function test_envelope_has_correct_subject(): void
    {
        Config::set('app.name', 'Test App');

        $user = User::factory()->make();
        $mailable = new LoginLinkNotification($user, 'dummy_token');

        $this->assertEquals(
            'Sign in to Test App',
            $mailable->envelope()->subject
        );
    }

    public function test_content_decrypts_token_and_generates_url(): void
    {
        $user = User::factory()->make();
        $rawToken = 'my-secret-token-123';
        $encryptedToken = Crypt::encryptString($rawToken);
        $expectedUrl = 'https://example.com/login/verify?token=my-secret-token-123';

        Config::set('auth.local.login_link_expiration_minutes', 15);

        URL::shouldReceive('route')
            ->once()
            ->with('login-link.verify', ['token' => $rawToken])
            ->andReturn($expectedUrl);

        $mailable = new LoginLinkNotification($user, $encryptedToken);

        $content = $mailable->content();

        $this->assertEquals('mail.auth.login-link', $content->markdown);
        $this->assertSame($user, $content->with['user']);
        $this->assertEquals(15, $content->with['expirationMinutes']);
        $this->assertEquals($expectedUrl, $content->with['url']);
    }
}
