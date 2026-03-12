<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Auth\Mail\AccessTokenExpirationNotification;
use App\Domains\Auth\Models\AccessToken;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAccessTokenExpirationNotificationsCommand extends Command
{
    protected $signature = 'access-tokens:notify-expiration';

    protected $description = 'Send expiration notifications for access tokens that are approaching their expiration date';

    public function handle(): int
    {
        if (! config('api.expiration_notifications.enabled')) {
            $this->components->info('Access Token expiration notifications are disabled in the configuration');

            return self::SUCCESS;
        }

        $intervals = config('api.expiration_notifications.intervals');

        $this->components->info('Checking for tokens expiring in: ' . implode(', ', $intervals) . ' days');
        $this->newLine();

        $totalNotificationsSent = 0;
        $totalErrors = 0;

        foreach ($intervals as $daysBeforeExpiration) {
            $query = $this->getExpiringTokensQuery($daysBeforeExpiration);
            $count = $query->count();

            if ($count === 0) {
                $this->components->info("No tokens expiring in {$daysBeforeExpiration} days");

                continue;
            }

            $this->components->info("Found {$count} token(s) expiring in {$daysBeforeExpiration} days");

            foreach ($query->lazyById(100) as $token) {
                try {
                    $this->sendExpirationNotificationForToken($token, $daysBeforeExpiration);
                    $totalNotificationsSent++;
                } catch (Throwable $e) {
                    $totalErrors++;
                    $this->logNotificationFailure($token, $e);
                }
            }
        }

        $this->newLine();
        $this->displaySummary($totalNotificationsSent, $totalErrors);

        return $totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Build query for tokens expiring within the specified number of days
     * that haven't been notified yet (or were last notified more than 24 hours ago).
     *
     * @return Builder<AccessToken>
     */
    private function getExpiringTokensQuery(int $daysBeforeExpiration): Builder
    {
        $now = Carbon::now(timezone: config('app.timezone'));
        $targetDate = $now->copy()->addDays($daysBeforeExpiration);

        return AccessToken::query()
            ->with(['user'])
            ->whereHas('user', function (\Illuminate\Contracts\Database\Query\Builder $query) {
                $query->whereNotNull('email')
                    ->where('email', '!=', config('mail.from.address'));
            })
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [
                $targetDate->copy()->startOfDay(),
                $targetDate->copy()->endOfDay(),
            ])
            ->where('expires_at', '>', $now)
            // Either never notified, or last notified more than 24 hours ago
            // This prevents spam if the command runs multiple times per day
            ->where(function (\Illuminate\Contracts\Database\Query\Builder $query) use ($now) {
                $query->whereNull('expiration_notified_at')
                    ->orWhere('expiration_notified_at', '<', $now->copy()->subHours(24));
            });
    }

    /**
     * Process a single token and send notification.
     */
    private function sendExpirationNotificationForToken(AccessToken $token, int $daysUntilExpiration): void
    {
        $user = $token->user;

        $this->line("⏳ Processing token for {$user->username} ({$user->email})");

        Mail::to($user->email)->queue(
            new AccessTokenExpirationNotification($user, $token, $daysUntilExpiration)
        );

        $token->update([
            'expiration_notified_at' => Carbon::now(),
        ]);

        $this->components->success("Email sent successfully to {$user->email}");
    }

    /**
     * Handle errors that occur during token processing.
     */
    private function logNotificationFailure(AccessToken $token, Throwable $e): void
    {
        $identifier = $token->user->username ?? "token #{$token->id}";

        $this->components->error("Failed to send notification for {$identifier}: {$e->getMessage()}");

        Log::error('Failed to send access token expiration notification', [
            'user_id' => $token->user?->id,
            'username' => $token->user?->username,
            'token_id' => $token->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    /**
     * Display summary of command execution.
     */
    private function displaySummary(int $totalSent, int $totalErrors): void
    {
        if ($totalSent === 0 && $totalErrors === 0) {
            $this->components->info('No notifications needed at this time');

            return;
        }

        $this->components->success("Successfully sent {$totalSent} notification(s)");

        if ($totalErrors > 0) {
            $this->components->error("Failed to send {$totalErrors} notification(s) - check logs for details");
        }
    }
}
