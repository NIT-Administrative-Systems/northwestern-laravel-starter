<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Console\Commands\SendAccessTokenExpirationNotificationsCommand;
use App\Domains\Auth\Mail\AccessTokenExpirationNotification;
use App\Domains\Auth\Models\AccessToken;
use App\Domains\User\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SendAccessTokenExpirationNotificationsCommand::class)]
class SendAccessTokenExpirationNotificationsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        config([
            'auth.api.expiration_notifications.enabled' => true,
            'auth.api.expiration_notifications.intervals' => [7, 30],
            'app.timezone' => 'UTC',
            'mail.from.address' => 'system@example.com',
        ]);
    }

    public function test_command_exits_successfully_when_notifications_are_disabled(): void
    {
        config(['auth.api.expiration_notifications.enabled' => false]);

        $this->artisan(SendAccessTokenExpirationNotificationsCommand::class)
            ->expectsOutputToContain('Access Token expiration notifications are disabled in the configuration')
            ->assertSuccessful();
    }

    public function test_command_handles_no_expiring_tokens_gracefully(): void
    {
        $this->artisan(SendAccessTokenExpirationNotificationsCommand::class)
            ->expectsOutputToContain('Checking for tokens expiring in: 7, 30 days')
            ->expectsOutputToContain('No tokens expiring in 7 days')
            ->expectsOutputToContain('No tokens expiring in 30 days')
            ->expectsOutputToContain('No notifications needed at this time')
            ->assertSuccessful();
    }

    public function test_command_successfully_sends_notifications_for_expiring_tokens(): void
    {
        $now = Carbon::create(2025, 10, 20, 10, 0, 0, 'UTC');
        Carbon::setTestNow($now);

        $sevenDaysFromNow = $now->copy()->addDays(7)->endOfDay();
        $thirtyDaysFromNow = $now->copy()->addDays(30)->startOfDay();

        // Token 1: Expiring in 7 days
        $token1 = AccessToken::factory()->for(User::factory()->create(['email' => 'user1@test.com']))->create(['name' => 'Token 1', 'expires_at' => $sevenDaysFromNow, 'expiration_notified_at' => null]);
        // Token 2: Expiring in 30 days
        $token2 = AccessToken::factory()->for(User::factory()->create(['email' => 'user2@test.com']))->create(['name' => 'Token 2', 'expires_at' => $thirtyDaysFromNow, 'expiration_notified_at' => null]);
        // Token 3: Expiring in 35 days (should be ignored)
        AccessToken::factory()->for(User::factory()->create(['email' => 'user3@test.com']))->create(['name' => 'Token 3', 'expires_at' => $now->copy()->addDays(35), 'expiration_notified_at' => null]);

        $this->artisan(SendAccessTokenExpirationNotificationsCommand::class)
            ->expectsOutputToContain('Found 1 token(s) expiring in 7 days')
            ->expectsOutputToContain("Email sent successfully to {$token1->user->email}")
            ->expectsOutputToContain('Found 1 token(s) expiring in 30 days')
            ->expectsOutputToContain("Email sent successfully to {$token2->user->email}")
            ->expectsOutputToContain('Successfully sent 2 notification(s)')
            ->assertSuccessful();

        Mail::assertQueued(AccessTokenExpirationNotification::class, 2);
        Mail::assertQueued(AccessTokenExpirationNotification::class, function (AccessTokenExpirationNotification $mail) use ($token1) {
            return $mail->token->is($token1) && $mail->daysUntilExpiration === 7;
        });
        Mail::assertQueued(AccessTokenExpirationNotification::class, function (AccessTokenExpirationNotification $mail) use ($token2) {
            return $mail->token->is($token2) && $mail->daysUntilExpiration === 30;
        });

        $this->assertNotNull($token1->fresh()->expiration_notified_at);
        $this->assertNotNull($token2->fresh()->expiration_notified_at);

        Carbon::setTestNow();
    }

    public function test_command_ignores_already_notified_tokens_within_24_hours(): void
    {
        $now = Carbon::create(2025, 10, 20, 10, 0, 0, 'UTC');
        Carbon::setTestNow($now);

        $sevenDaysFromNow = $now->copy()->addDays(7)->endOfDay();

        // Token 1: Notified 1 hour ago (within 24 hours, should be ignored)
        AccessToken::factory()->for(User::factory())->create([
            'name' => 'Recently Notified',
            'expires_at' => $sevenDaysFromNow,
            'expiration_notified_at' => $now->copy()->subHours(1),
        ]);

        // Token 2: Notified 25 hours ago (outside 24 hours, should be processed)
        $token2 = AccessToken::factory()->for(User::factory()->create(['email' => 'process@test.com']))->create([
            'name' => 'Needs Notification',
            'expires_at' => $sevenDaysFromNow,
            'expiration_notified_at' => $now->copy()->subHours(25),
        ]);

        $this->artisan(SendAccessTokenExpirationNotificationsCommand::class)
            ->expectsOutputToContain('Found 1 token(s) expiring in 7 days')
            ->expectsOutputToContain("Email sent successfully to {$token2->user->email}")
            ->expectsOutputToContain('Successfully sent 1 notification(s)')
            ->assertSuccessful();

        Mail::assertQueued(AccessTokenExpirationNotification::class, 1);

        Carbon::setTestNow();
    }

    public function test_command_handles_exceptions_during_notification_process(): void
    {
        $now = Carbon::create(2025, 10, 20, 10, 0, 0, 'UTC');
        Carbon::setTestNow($now);

        $sevenDaysFromNow = $now->copy()->addDays(7)->endOfDay();
        $failingToken = AccessToken::factory()->for(User::factory())->create(['name' => 'Failing Token', 'expires_at' => $sevenDaysFromNow, 'expiration_notified_at' => null]);

        Mail::shouldReceive('to')->once()->andThrow(new \Exception('SMTP connection failure'));

        $this->artisan(SendAccessTokenExpirationNotificationsCommand::class)
            ->expectsOutputToContain('Found 1 token(s) expiring in 7 days')
            ->expectsOutputToContain("Failed to send notification for {$failingToken->user->username}: SMTP connection failure")
            ->expectsOutputToContain('Failed to send 1 notification(s) - check logs for details')
            ->assertFailed();

        $this->assertNull($failingToken->fresh()->expiration_notified_at);

        Carbon::setTestNow();
    }

    public function test_command_excludes_tokens_for_system_email_and_revoked_tokens(): void
    {
        $now = Carbon::create(2025, 10, 20, 10, 0, 0, 'UTC');
        Carbon::setTestNow($now);
        $sevenDaysFromNow = $now->copy()->addDays(7)->endOfDay();

        $validToken = AccessToken::factory()->for(User::factory())->create(['name' => 'Valid Token', 'expires_at' => $sevenDaysFromNow, 'revoked_at' => null]);

        $systemUser = User::factory()->create(['email' => config('mail.from.address')]);
        AccessToken::factory()->for($systemUser)->create(['name' => 'System Token', 'expires_at' => $sevenDaysFromNow]);

        AccessToken::factory()->for(User::factory())->create(['name' => 'Revoked Token', 'expires_at' => $sevenDaysFromNow, 'revoked_at' => $now]);

        $this->artisan(SendAccessTokenExpirationNotificationsCommand::class)
            ->expectsOutputToContain('Found 1 token(s) expiring in 7 days')
            ->expectsOutputToContain("Email sent successfully to {$validToken->user->email}")
            ->expectsOutputToContain('Successfully sent 1 notification(s)')
            ->assertSuccessful();

        Mail::assertQueued(AccessTokenExpirationNotification::class, 1);

        Carbon::setTestNow();
    }
}
