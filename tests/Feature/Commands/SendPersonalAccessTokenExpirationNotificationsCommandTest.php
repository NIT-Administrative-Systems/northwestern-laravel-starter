<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Console\Commands\SendPersonalAccessTokenExpirationNotificationsCommand;
use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Api\Mail\PersonalAccessTokenExpirationNotification;
use App\Domains\User\Data\UserPreferences;
use App\Domains\User\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\IssuesPersonalAccessTokens;
use Tests\TestCase;

#[CoversClass(SendPersonalAccessTokenExpirationNotificationsCommand::class)]
final class SendPersonalAccessTokenExpirationNotificationsCommandTest extends TestCase
{
    use IssuesPersonalAccessTokens;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        config([
            'api.personal_access_tokens.expiration_notifications.enabled' => true,
            'api.personal_access_tokens.expiration_notifications.intervals' => [7],
            'app.timezone' => 'UTC',
            'mail.from.address' => 'system@example.com',
        ]);

        $this->travelTo(Carbon::create(2025, 10, 20, 10, 0, 0, 'UTC'));
    }

    public function test_it_emails_people_whose_tokens_expire_soon(): void
    {
        $user = User::factory()->create(['email' => 'willie@example.edu']);
        $token = $this->expiringToken($user);

        $this->artisan(SendPersonalAccessTokenExpirationNotificationsCommand::class)
            ->expectsOutputToContain('Sent 1 notification(s)')
            ->assertSuccessful();

        Mail::assertQueued(PersonalAccessTokenExpirationNotification::class, fn (PersonalAccessTokenExpirationNotification $mail) => $mail->token->is($token) && $mail->daysUntilExpiration === 7 && $mail->hasTo('willie@example.edu'));
        $this->assertNotNull($token->fresh()?->expiration_notified_at);
    }

    public function test_it_respects_the_preference_and_skips_recent_and_revoked_tokens(): void
    {
        $optedOut = User::factory()->create(['preferences' => new UserPreferences(emailBeforeAccessTokensExpire: false)]);
        $this->expiringToken($optedOut);

        $recent = $this->expiringToken(User::factory()->create());
        $recent->forceFill(['expiration_notified_at' => now()->subHour()])->save();

        $this->expiringToken(User::factory()->create())->revoke();

        $this->artisan(SendPersonalAccessTokenExpirationNotificationsCommand::class)
            ->expectsOutputToContain('Sent 0 notification(s)')
            ->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_a_failed_email_is_reported_and_retried_on_the_next_run(): void
    {
        $token = $this->expiringToken(User::factory()->create());
        $token->forceFill(['name' => 'Nightly export'])->save();

        Mail::shouldReceive('to')->once()->andThrow(new \Exception('SMTP connection failure'));

        $this->artisan(SendPersonalAccessTokenExpirationNotificationsCommand::class)
            ->expectsOutputToContain('Failed to send notification for token Nightly export: SMTP connection failure')
            ->expectsOutputToContain('Sent 0 notification(s), 1 failed')
            ->assertFailed();

        $this->assertNull($token->fresh()?->expiration_notified_at);
    }

    public function test_it_does_nothing_when_disabled(): void
    {
        config(['api.personal_access_tokens.expiration_notifications.enabled' => false]);
        $this->expiringToken(User::factory()->create());

        $this->artisan(SendPersonalAccessTokenExpirationNotificationsCommand::class)->assertSuccessful();

        Mail::assertNothingQueued();
    }

    // Turning off reminders to integration owners must not stop the ones people opted into.
    public function test_it_runs_when_client_secret_reminders_are_off(): void
    {
        config(['api.client_secret_expiration_notifications.enabled' => false]);
        $this->expiringToken(User::factory()->create());

        $this->artisan(SendPersonalAccessTokenExpirationNotificationsCommand::class)
            ->expectsOutputToContain('Sent 1 notification(s)')
            ->assertSuccessful();
    }

    private function expiringToken(User $user): \App\Domains\Api\Models\OAuthToken
    {
        [, $token] = $this->personalAccessToken($user);
        $token->forceFill(['expires_at' => now()->addDays(7)->setTime(15, 0)])->save();

        return $token;
    }

    // Nobody is warned about a token that already doesn't work.
    public function test_nobody_is_warned_about_a_token_they_can_no_longer_use(): void
    {
        $lost = User::factory()->create(['email' => 'lost@example.edu']);
        $this->expiringToken($lost);
        $lost->revokePermissionTo(SystemPermission::CreatePersonalAccessTokens);

        $this->artisan(SendPersonalAccessTokenExpirationNotificationsCommand::class)->assertSuccessful();
        Mail::assertNothingQueued();

        $this->expiringToken(User::factory()->create(['email' => 'willie@example.edu']));
        config(['api.enabled' => false]);

        $this->artisan(SendPersonalAccessTokenExpirationNotificationsCommand::class)->assertSuccessful();
        Mail::assertNothingQueued();
    }
}
