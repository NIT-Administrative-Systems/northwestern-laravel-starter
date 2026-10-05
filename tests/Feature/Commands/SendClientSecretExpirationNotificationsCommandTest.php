<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Console\Commands\SendClientSecretExpirationNotificationsCommand;
use App\Domains\Auth\Actions\Api\CreateServiceClient;
use App\Domains\Auth\Mail\ClientSecretExpirationNotification;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\User\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SendClientSecretExpirationNotificationsCommand::class)]
final class SendClientSecretExpirationNotificationsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        config([
            'api.expiration_notifications.enabled' => true,
            'api.expiration_notifications.intervals' => [7, 30],
            'app.timezone' => 'UTC',
            'mail.from.address' => 'system@example.com',
        ]);

        $this->travelTo(Carbon::create(2025, 10, 20, 10, 0, 0, 'UTC'));
    }

    public function test_command_exits_successfully_when_notifications_are_disabled(): void
    {
        config(['api.expiration_notifications.enabled' => false]);

        $this->artisan(SendClientSecretExpirationNotificationsCommand::class)
            ->expectsOutputToContain('Client secret expiration notifications are disabled in the configuration')
            ->assertSuccessful();
    }

    public function test_command_handles_no_expiring_secrets_gracefully(): void
    {
        $this->artisan(SendClientSecretExpirationNotificationsCommand::class)
            ->expectsOutputToContain('Checking for client secrets expiring in: 7, 30 days')
            ->expectsOutputToContain('No client secrets expiring in 7 days')
            ->expectsOutputToContain('No client secrets expiring in 30 days')
            ->expectsOutputToContain('No notifications needed at this time')
            ->assertSuccessful();
    }

    public function test_command_sends_notifications_for_expiring_secrets(): void
    {
        $inSevenDays = $this->client('user1@test.com', now()->addDays(7)->endOfDay());
        $inThirtyDays = $this->client('user2@test.com', now()->addDays(30)->startOfDay());
        $this->client('user3@test.com', now()->addDays(35));

        $this->artisan(SendClientSecretExpirationNotificationsCommand::class)
            ->expectsOutputToContain('Found 1 client secret(s) expiring in 7 days')
            ->expectsOutputToContain('Email sent successfully to user1@test.com')
            ->expectsOutputToContain('Found 1 client secret(s) expiring in 30 days')
            ->expectsOutputToContain('Email sent successfully to user2@test.com')
            ->expectsOutputToContain('Successfully sent 2 notification(s)')
            ->assertSuccessful();

        Mail::assertQueued(ClientSecretExpirationNotification::class, 2);
        Mail::assertQueued(ClientSecretExpirationNotification::class, fn (ClientSecretExpirationNotification $mail) => $mail->client->is($inSevenDays) && $mail->daysUntilExpiration === 7);
        Mail::assertQueued(ClientSecretExpirationNotification::class, fn (ClientSecretExpirationNotification $mail) => $mail->client->is($inThirtyDays) && $mail->daysUntilExpiration === 30);

        $this->assertNotNull($inSevenDays->fresh()?->secret_expiration_notified_at);
        $this->assertNotNull($inThirtyDays->fresh()?->secret_expiration_notified_at);
    }

    public function test_command_ignores_clients_notified_within_24_hours(): void
    {
        $this->client('recent@test.com', now()->addDays(7)->endOfDay(), notifiedAt: now()->subHour());
        $this->client('process@test.com', now()->addDays(7)->endOfDay(), notifiedAt: now()->subHours(25));

        $this->artisan(SendClientSecretExpirationNotificationsCommand::class)
            ->expectsOutputToContain('Found 1 client secret(s) expiring in 7 days')
            ->expectsOutputToContain('Email sent successfully to process@test.com')
            ->assertSuccessful();

        Mail::assertQueued(ClientSecretExpirationNotification::class, 1);
    }

    public function test_command_handles_exceptions_during_notification(): void
    {
        $failing = $this->client('failing@test.com', now()->addDays(7)->endOfDay(), name: 'Failing Client');

        Mail::shouldReceive('to')->once()->andThrow(new \Exception('SMTP connection failure'));

        $this->artisan(SendClientSecretExpirationNotificationsCommand::class)
            ->expectsOutputToContain('Failed to send notification for client Failing Client: SMTP connection failure')
            ->expectsOutputToContain('Failed to send 1 notification(s) - check logs for details')
            ->assertFailed();

        $this->assertNull($failing->fresh()?->secret_expiration_notified_at);
    }

    public function test_command_skips_system_addresses_missing_addresses_and_revoked_clients(): void
    {
        $this->client('valid@test.com', now()->addDays(7)->endOfDay());
        $this->client('system@example.com', now()->addDays(7)->endOfDay());
        $this->client(null, now()->addDays(7)->endOfDay());
        $this->client('revoked@test.com', now()->addDays(7)->endOfDay())->forceFill(['revoked' => true])->save();

        $this->artisan(SendClientSecretExpirationNotificationsCommand::class)
            ->expectsOutputToContain('Found 1 client secret(s) expiring in 7 days')
            ->expectsOutputToContain('Email sent successfully to valid@test.com')
            ->assertSuccessful();

        Mail::assertQueued(ClientSecretExpirationNotification::class, 1);
    }

    private function client(?string $email, Carbon $secretExpiresAt, ?Carbon $notifiedAt = null, string $name = 'Sync'): OAuthClient
    {
        [, $client] = resolve(CreateServiceClient::class)(User::factory()->api()->create(['email' => $email]), $name, $secretExpiresAt);

        $client->forceFill(['secret_expiration_notified_at' => $notifiedAt])->save();

        return $client;
    }
}
