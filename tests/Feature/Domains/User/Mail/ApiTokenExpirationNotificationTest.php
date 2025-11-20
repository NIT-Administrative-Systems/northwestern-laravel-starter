<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Mail;

use App\Domains\User\Mail\ApiTokenExpirationNotification;
use App\Domains\User\Models\ApiToken;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ApiTokenExpirationNotification::class)]
class ApiTokenExpirationNotificationTest extends TestCase
{
    public function test_envelope_has_correct_subject(): void
    {
        $user = User::factory()->make();
        $token = new ApiToken();

        $mailable = new ApiTokenExpirationNotification($user, $token, 5);

        $this->assertEquals(
            'API Token Expiring Soon - Action Required',
            $mailable->envelope()->subject
        );
    }

    public function test_content_has_correct_view_and_data(): void
    {
        $user = User::factory()->make();

        $expirationDate = now()->addDays(5);
        $token = ApiToken::factory()->make(['valid_to' => $expirationDate]);

        $mailable = new ApiTokenExpirationNotification($user, $token, 5);

        $content = $mailable->content();

        $this->assertEquals('mail.api-token-expiration', $content->markdown);

        $this->assertSame($user, $content->with['user']);
        $this->assertSame($token, $content->with['token']);
        $this->assertEquals(5, $content->with['daysUntilExpiration']);

        $expectedDateString = $expirationDate->format('F j, Y \a\t g:i A T');
        $this->assertEquals($expectedDateString, $content->with['expirationDate']);
    }
}
