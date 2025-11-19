<?php

declare(strict_types=1);

namespace App\Domains\User\Actions;

use App\Domains\User\Mail\LoginLinkNotification;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginLink;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Generates and sends a login link for local user authentication.
 *
 * Login links are:
 * - Time-limited
 * - Single-use
 * - Rate-limited
 */
readonly class SendLoginLink
{
    public function __invoke(User $user, ?string $requestedIpAddress = null): UserLoginLink
    {
        if (! $user->is_local_user) {
            throw new RuntimeException('Login links can only be sent to local users.');
        }

        $rateLimitKey = "login-link:{$user->email}";
        $maxAttempts = config('auth.local.rate_limit_per_hour');

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $minutes = ceil($seconds / 60);

            throw new RuntimeException(
                "Too many login attempts. Please try again in {$minutes} minute(s)."
            );
        }

        $rawToken = Str::random(length: 64);
        $hashedToken = UserLoginLink::hashFromPlain($rawToken);

        $loginLink = DB::transaction(function () use ($user, $hashedToken, $requestedIpAddress, $rateLimitKey) {
            $link = $user->login_links()->create([
                'token' => $hashedToken,
                'email' => $user->email,
                'expires_at' => Carbon::now()->addMinutes(
                    (int) config('auth.local.login_link_expiration_minutes')
                ),
                'requested_ip_address' => $requestedIpAddress ?? null,
            ]);

            RateLimiter::hit($rateLimitKey, (int) CarbonInterval::hour()->totalSeconds);

            return $link;
        });

        $encryptedToken = Crypt::encryptString($rawToken);

        Mail::to($user->email)->queue(
            new LoginLinkNotification($user, $encryptedToken)
        );

        return $loginLink;
    }
}
