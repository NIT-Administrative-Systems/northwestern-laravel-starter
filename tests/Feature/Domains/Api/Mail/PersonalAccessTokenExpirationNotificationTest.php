<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Api\Mail;

use App\Domains\Api\Mail\PersonalAccessTokenExpirationNotification;
use App\Domains\Api\Models\OAuthToken;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(PersonalAccessTokenExpirationNotification::class)]
final class PersonalAccessTokenExpirationNotificationTest extends TestCase
{
    public function test_it_names_the_token_and_links_to_the_account_area(): void
    {
        $user = User::factory()->make(['first_name' => 'Willie']);
        $token = (new OAuthToken())->forceFill(['name' => 'Nightly export', 'expires_at' => now()->addDays(3)]);

        $mail = new PersonalAccessTokenExpirationNotification($user, $token, 3);

        $this->assertSame('Your personal access token expires in three days', $mail->envelope()->subject);
        $mail->assertSeeInHtml('Nightly export')
            ->assertSeeInHtml('/app/account/access-tokens')
            ->assertSeeInHtml('/app/account/preferences');
    }
}
