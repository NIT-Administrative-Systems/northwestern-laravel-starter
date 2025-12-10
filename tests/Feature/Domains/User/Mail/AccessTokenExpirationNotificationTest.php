<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Mail;

use App\Domains\User\Mail\AccessTokenExpirationNotification;
use App\Domains\User\Models\AccessToken;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AccessTokenExpirationNotification::class)]
class AccessTokenExpirationNotificationTest extends TestCase
{
    public function test_envelope_has_correct_subject(): void
    {
        $user = User::factory()->make();
        $token = new AccessToken();

        $mailable = new AccessTokenExpirationNotification($user, $token, 5);

        $this->assertEquals(
            'Access Token Expiring Soon - Action Required',
            $mailable->envelope()->subject
        );
    }

    public function test_content_has_correct_view_and_data(): void
    {
        $user = User::factory()->make();

        $expirationDate = now()->addDays(5);
        $token = AccessToken::factory()->make(['name' => 'Expiring Token', 'expires_at' => $expirationDate]);

        $mailable = new AccessTokenExpirationNotification($user, $token, 5);

        $content = $mailable->content();

        $this->assertEquals('mail.access-token-expiration', $content->markdown);

        $this->assertSame($user, $content->with['user']);
        $this->assertSame($token, $content->with['token']);
        $this->assertEquals(5, $content->with['daysUntilExpiration']);

        $expectedDateString = $expirationDate->format('F j, Y \a\t g:i A T');
        $this->assertEquals($expectedDateString, $content->with['expirationDate']);
    }
}
