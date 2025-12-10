<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\User\Mail\AccessTokenExpirationNotification;
use App\Domains\User\Models\AccessToken;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
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
        if (! config('auth.api.expiration_notifications.enabled')) {
            $this->components->info('Access Token expiration notifications are disabled in the configuration');

            return self::SUCCESS;
        }

        $intervals = config('auth.api.expiration_notifications.intervals');

        $this->components->info('Checking for tokens expiring in: ' . implode(', ', $intervals) . ' days');
        $this->newLine();

        $totalNotificationsSent = 0;
        $totalErrors = 0;

        foreach ($intervals as $daysBeforeExpiration) {
            $tokens = $this->getExpiringTokens($daysBeforeExpiration);

            if ($tokens->isEmpty()) {
                $this->components->info("No tokens expiring in {$daysBeforeExpiration} days");

                continue;
            }

            $this->components->info("Found {$tokens->count()} token(s) expiring in {$daysBeforeExpiration} days");

            foreach ($tokens as $token) {
                try {
                    $this->processToken($token, $daysBeforeExpiration);
                    $totalNotificationsSent++;
                } catch (Throwable $e) {
                    $totalErrors++;
                    $this->handleError($token, $e);
                }
            }
        }

        $this->newLine();
        $this->displaySummary($totalNotificationsSent, $totalErrors);

        return $totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Get tokens that are expiring within the specified number of days
     * and haven't been notified yet (or were last notified more than 24 hours ago).
     *
     * @return Collection<int, AccessToken>
     */
    private function getExpiringTokens(int $daysBeforeExpiration): Collection
    {
        $now = Carbon::now(timezone: config('app.timezone'));
        $targetDate = $now->copy()->addDays($daysBeforeExpiration);

        return AccessToken::query()
            ->with(['user'])
            ->whereHas('user', function ($query) {
                $query->whereNotNull('email')
                    ->where('email', '!=', config('mail.from.address'));
            })
            ->whereNull('revoked_at')
            ->whereNotNull('valid_to')
            ->whereBetween('valid_to', [
                $targetDate->copy()->startOfDay(),
                $targetDate->copy()->endOfDay(),
            ])
            ->where('valid_to', '>', $now)
            // Either never notified, or last notified more than 24 hours ago
            // This prevents spam if the command runs multiple times per day
            ->where(function ($query) use ($now) {
                $query->whereNull('expiration_notified_at')
                    ->orWhere('expiration_notified_at', '<', $now->copy()->subHours(24));
            })
            ->get();
    }

    /**
     * Process a single token and send notification.
     */
    private function processToken(AccessToken $token, int $daysUntilExpiration): void
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
    private function handleError(AccessToken $token, Throwable $e): void
    {
        $user = $token->user;

        $this->components->error("Failed to send notification for {$user->username}: {$e->getMessage()}");

        Log::error('Failed to send access token expiration notification', [
            'user_id' => $user->id,
            'username' => $user->username,
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
