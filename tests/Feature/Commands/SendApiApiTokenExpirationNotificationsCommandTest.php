<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Console\Commands\SendApiTokenExpirationNotificationsCommand;
use App\Domains\User\Mail\ApiTokenExpirationNotification;
use App\Domains\User\Models\ApiToken;
use App\Domains\User\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SendApiTokenExpirationNotificationsCommand::class)]
class SendApiApiTokenExpirationNotificationsCommandTest extends TestCase
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

        $this->artisan(SendApiTokenExpirationNotificationsCommand::class)
            ->expectsOutputToContain('API token expiration notifications are disabled in the configuration')
            ->assertSuccessful();
    }

    public function test_command_handles_no_expiring_tokens_gracefully(): void
    {
        $this->artisan(SendApiTokenExpirationNotificationsCommand::class)
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
        $token1 = ApiToken::factory()->for(User::factory()->create(['email' => 'user1@test.com']))->create(['valid_to' => $sevenDaysFromNow, 'expiration_notified_at' => null]);
        // Token 2: Expiring in 30 days
        $token2 = ApiToken::factory()->for(User::factory()->create(['email' => 'user2@test.com']))->create(['valid_to' => $thirtyDaysFromNow, 'expiration_notified_at' => null]);
        // Token 3: Expiring in 35 days (should be ignored)
        ApiToken::factory()->for(User::factory()->create(['email' => 'user3@test.com']))->create(['valid_to' => $now->copy()->addDays(35), 'expiration_notified_at' => null]);

        $this->artisan(SendApiTokenExpirationNotificationsCommand::class)
            ->expectsOutputToContain('Found 1 token(s) expiring in 7 days')
            ->expectsOutputToContain("Email sent successfully to {$token1->user->email}")
            ->expectsOutputToContain('Found 1 token(s) expiring in 30 days')
            ->expectsOutputToContain("Email sent successfully to {$token2->user->email}")
            ->expectsOutputToContain('Successfully sent 2 notification(s)')
            ->assertSuccessful();

        Mail::assertQueued(ApiTokenExpirationNotification::class, 2);
        Mail::assertQueued(ApiTokenExpirationNotification::class, function (ApiTokenExpirationNotification $mail) use ($token1) {
            return $mail->token->is($token1) && $mail->daysUntilExpiration === 7;
        });
        Mail::assertQueued(ApiTokenExpirationNotification::class, function (ApiTokenExpirationNotification $mail) use ($token2) {
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
        ApiToken::factory()->for(User::factory())->create([
            'valid_to' => $sevenDaysFromNow,
            'expiration_notified_at' => $now->copy()->subHours(1),
        ]);

        // Token 2: Notified 25 hours ago (outside 24 hours, should be processed)
        $token2 = ApiToken::factory()->for(User::factory()->create(['email' => 'process@test.com']))->create([
            'valid_to' => $sevenDaysFromNow,
            'expiration_notified_at' => $now->copy()->subHours(25),
        ]);

        $this->artisan(SendApiTokenExpirationNotificationsCommand::class)
            ->expectsOutputToContain('Found 1 token(s) expiring in 7 days')
            ->expectsOutputToContain("Email sent successfully to {$token2->user->email}")
            ->expectsOutputToContain('Successfully sent 1 notification(s)')
            ->assertSuccessful();

        Mail::assertQueued(ApiTokenExpirationNotification::class, 1);

        Carbon::setTestNow();
    }

    public function test_command_handles_exceptions_during_notification_process(): void
    {
        $now = Carbon::create(2025, 10, 20, 10, 0, 0, 'UTC');
        Carbon::setTestNow($now);

        $sevenDaysFromNow = $now->copy()->addDays(7)->endOfDay();
        $failingToken = ApiToken::factory()->for(User::factory())->create(['valid_to' => $sevenDaysFromNow, 'expiration_notified_at' => null]);

        Mail::shouldReceive('to')->once()->andThrow(new \Exception('SMTP connection failure'));

        $this->artisan(SendApiTokenExpirationNotificationsCommand::class)
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

        $validToken = ApiToken::factory()->for(User::factory())->create(['valid_to' => $sevenDaysFromNow, 'revoked_at' => null]);

        $systemUser = User::factory()->create(['email' => config('mail.from.address')]);
        ApiToken::factory()->for($systemUser)->create(['valid_to' => $sevenDaysFromNow]);

        ApiToken::factory()->for(User::factory())->create(['valid_to' => $sevenDaysFromNow, 'revoked_at' => $now]);

        $this->artisan(SendApiTokenExpirationNotificationsCommand::class)
            ->expectsOutputToContain('Found 1 token(s) expiring in 7 days')
            ->expectsOutputToContain("Email sent successfully to {$validToken->user->email}")
            ->expectsOutputToContain('Successfully sent 1 notification(s)')
            ->assertSuccessful();

        Mail::assertQueued(ApiTokenExpirationNotification::class, 1);

        Carbon::setTestNow();
    }
}
