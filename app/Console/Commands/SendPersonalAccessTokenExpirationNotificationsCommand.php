<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Api\CredentialAccess;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Api\Mail\PersonalAccessTokenExpirationNotification;
use App\Domains\Api\Models\OAuthToken;
use App\Domains\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails people about each of their personal access tokens that is about to expire, at the
 * intervals in `api.personal_access_tokens.expiration_notifications.intervals`, unless they turned the emails off
 * on the Account area's Preferences page.
 */
class SendPersonalAccessTokenExpirationNotificationsCommand extends Command
{
    protected $signature = 'personal-access-tokens:notify-expiration';

    protected $description = 'Send expiration notifications for personal access tokens that are approaching their expiration date';

    public function handle(CredentialAccess $credentials): int
    {
        if (! config('api.personal_access_tokens.expiration_notifications.enabled')) {
            $this->components->info('Personal access token expiration notifications are disabled in the configuration');

            return self::SUCCESS;
        }

        $sent = 0;
        $errors = 0;

        foreach (config('api.personal_access_tokens.expiration_notifications.intervals') as $daysBeforeExpiration) {
            foreach ($this->expiringTokensQuery($daysBeforeExpiration)->lazyById(100) as $token) {
                $user = User::query()->find($token->user_id);

                // Nobody is warned about a token they can no longer use, such as while the API is off.
                if (! $user instanceof User
                    || ! $user->preferences->emailBeforeAccessTokensExpire
                    || ! $credentials->decide($user, CredentialOperation::Use, CredentialKind::PersonalAccessToken, $user)->allowed) {
                    continue;
                }

                try {
                    Mail::to($user->email)->queue(new PersonalAccessTokenExpirationNotification($user, $token, $daysBeforeExpiration));
                    $token->forceFill(['expiration_notified_at' => Carbon::now()])->save();
                    $sent++;
                } catch (Throwable $e) {
                    $errors++;
                    $this->components->error("Failed to send notification for token {$token->name}: {$e->getMessage()}");
                    Log::error('Failed to send personal access token expiration notification', [
                        'token_id' => $token->getKey(),
                        'user_id' => $token->user_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->components->info("Sent {$sent} notification(s)" . ($errors > 0 ? ", {$errors} failed" : ''));

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Active personal tokens expiring on the day `$daysBeforeExpiration` days from now, whose
     * owner has an email address, and not notified in the last 24 hours.
     *
     * @return Builder<OAuthToken>
     */
    private function expiringTokensQuery(int $daysBeforeExpiration): Builder
    {
        $now = Carbon::now(timezone: config('app.timezone'));
        $target = $now->copy()->addDays($daysBeforeExpiration);

        return OAuthToken::query()
            ->personal()
            ->where('revoked', false)
            ->whereBetween('expires_at', [$target->copy()->startOfDay(), $target->copy()->endOfDay()])
            ->where('expires_at', '>', $now)
            ->whereIn('user_id', User::query()->whereNotNull('email')->where('email', '!=', config('mail.from.address'))->select('id'))
            ->where(fn (Builder $query) => $query
                ->whereNull('expiration_notified_at')
                ->orWhere('expiration_notified_at', '<', $now->copy()->subHours(24)));
    }
}
