<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Mail;

use App\Domains\Auth\Mail\ClientSecretExpirationNotification;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ClientSecretExpirationNotification::class)]
final class ClientSecretExpirationNotificationTest extends TestCase
{
    public function test_envelope_has_correct_subject(): void
    {
        $mailable = new ClientSecretExpirationNotification(User::factory()->make(), new OAuthClient(), 5);

        $this->assertSame('API Client Secret Expiring Soon - Action Required', $mailable->envelope()->subject);
    }

    public function test_content_has_correct_view_and_data(): void
    {
        $user = User::factory()->make();
        $expirationDate = now()->addDays(5);
        $client = (new OAuthClient())->forceFill(['name' => 'Nightly sync', 'secret_expires_at' => $expirationDate]);

        $content = (new ClientSecretExpirationNotification($user, $client, 5))->content();

        $this->assertSame('mail.client-secret-expiration', $content->markdown);
        $this->assertSame($user, $content->with['user']);
        $this->assertSame($client, $content->with['client']);
        $this->assertSame(5, $content->with['daysUntilExpiration']);
        $this->assertSame($expirationDate->format('F j, Y \a\t g:i A T'), $content->with['expirationDate']);
    }

    public function test_it_renders_the_client_and_what_to_do(): void
    {
        $user = User::factory()->api()->make(['username' => 'api-sync']);
        $client = (new OAuthClient())->forceFill(['id' => '019a0000-0000-7000-8000-000000000009', 'name' => 'Nightly sync', 'secret_expires_at' => now()->addDays(3)]);

        (new ClientSecretExpirationNotification($user, $client, 3))
            ->assertSeeInHtml('Nightly sync')
            ->assertSeeInHtml('019a0000-0000-7000-8000-000000000009')
            ->assertSeeInHtml('Immediate Action Required');
    }
}
