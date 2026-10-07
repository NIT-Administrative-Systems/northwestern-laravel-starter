<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Api\CredentialAccess;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Auth\Mail\ClientSecretExpirationNotification;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails an API user's contact address about each service client whose secret is about to
 * expire, at the intervals in `api.client_secret_expiration_notifications.intervals`. A client stops
 * working when its secret expires, so its owner needs time to rotate it.
 */
class SendClientSecretExpirationNotificationsCommand extends Command
{
    protected $signature = 'oauth-clients:notify-secret-expiration';

    protected $description = 'Send expiration notifications for service client secrets that are approaching their expiration date';

    public function handle(CredentialAccess $credentials): int
    {
        if (! config('api.client_secret_expiration_notifications.enabled')) {
            $this->components->info('Client secret expiration notifications are disabled in the configuration');

            return self::SUCCESS;
        }

        $intervals = config('api.client_secret_expiration_notifications.intervals');

        $this->components->info('Checking for client secrets expiring in: ' . implode(', ', $intervals) . ' days');
        $this->newLine();

        $totalNotificationsSent = 0;
        $totalErrors = 0;

        foreach ($intervals as $daysBeforeExpiration) {
            $query = $this->expiringClientsQuery($daysBeforeExpiration);
            $count = $query->count();

            if ($count === 0) {
                $this->components->info("No client secrets expiring in {$daysBeforeExpiration} days");

                continue;
            }

            $this->components->info("Found {$count} client secret(s) expiring in {$daysBeforeExpiration} days");

            foreach ($query->lazyById(100) as $client) {
                // Nobody is warned about a client its API user can no longer use, such as while the API is off.
                $owner = $client->owner;

                if (! $owner instanceof User || ! $credentials->decide($owner, CredentialOperation::Use, CredentialKind::ServiceClient, $owner)->allowed) {
                    continue;
                }

                try {
                    $this->notify($client, $daysBeforeExpiration);
                    $totalNotificationsSent++;
                } catch (Throwable $e) {
                    $totalErrors++;
                    $this->logNotificationFailure($client, $e);
                }
            }
        }

        $this->newLine();
        $this->displaySummary($totalNotificationsSent, $totalErrors);

        return $totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Active clients whose secret expires on the day `$daysBeforeExpiration` days from now,
     * owned by an API user with a contact email, and not notified in the last 24 hours.
     *
     * @return Builder<OAuthClient>
     */
    private function expiringClientsQuery(int $daysBeforeExpiration): Builder
    {
        $now = Carbon::now(timezone: config('app.timezone'));
        $targetDate = $now->copy()->addDays($daysBeforeExpiration);

        return OAuthClient::query()
            ->with('owner')
            ->whereHasMorph('owner', [User::class], function (Builder $query): void {
                $query->whereNotNull('email')
                    ->where('email', '!=', config('mail.from.address'));
            })
            ->where('revoked', false)
            ->whereBetween('secret_expires_at', [
                $targetDate->copy()->startOfDay(),
                $targetDate->copy()->endOfDay(),
            ])
            ->where('secret_expires_at', '>', $now)
            // Either never notified, or last notified more than 24 hours ago, so running the
            // command more than once a day doesn't send duplicates.
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('secret_expiration_notified_at')
                    ->orWhere('secret_expiration_notified_at', '<', $now->copy()->subHours(24));
            });
    }

    private function notify(OAuthClient $client, int $daysUntilExpiration): void
    {
        /** @var User $user */
        $user = $client->owner;

        $this->line("⏳ Processing client {$client->name} for {$user->username} ({$user->email})");

        Mail::to($user->email)->queue(new ClientSecretExpirationNotification($user, $client, $daysUntilExpiration));

        $client->forceFill(['secret_expiration_notified_at' => Carbon::now()])->save();

        $this->components->success("Email sent successfully to {$user->email}");
    }

    private function logNotificationFailure(OAuthClient $client, Throwable $e): void
    {
        $this->components->error("Failed to send notification for client {$client->name}: {$e->getMessage()}");

        Log::error('Failed to send client secret expiration notification', [
            'oauth_client_id' => $client->getKey(),
            'error' => $e->getMessage(),
            'exception_class' => $e::class,
            'trace' => $e->getTraceAsString(),
        ]);
    }

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
