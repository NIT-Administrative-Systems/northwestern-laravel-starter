<?php

declare(strict_types=1);

namespace App\Domains\User\Actions\Local;

use App\Domains\User\Mail\LoginVerificationCodeNotification;
use App\Domains\User\Models\LoginChallenge;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Issues a one-time {@see LoginChallenge} for a given email address.
 *
 * This action:
 * - Normalizes the email address
 * - Enforces per-email rate limiting to reduce enumeration attempts
 * - Generates a numeric verification code and stores only its hash
 * - Persists a {@see LoginChallenge} record with an expiry and request metadata
 * - Queues a {@see LoginVerificationCodeNotification} email to the user
 */
final readonly class IssueLoginChallenge
{
    public function __construct(
        private GenerateOneTimeCode $generateOneTimeCode,
    ) {
        //
    }

    public function __invoke(string $email, ?string $ip, ?string $userAgent): LoginChallenge
    {
        $now = new CarbonImmutable();
        $email = mb_strtolower(trim($email));

        $rateLimitKey = "login-code:{$email}";
        $maxAttempts = (int) config('auth.local.rate_limit_per_hour');

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $minutes = (int) ceil($seconds / 60);

            throw new RuntimeException(
                "Too many login attempts. Please try again in {$minutes} minute(s)."
            );
        }

        $digits = (int) config('auth.local.code.digits', 6);
        $expires = (int) config('auth.local.code.expires_in_minutes', 10);
        $code = ($this->generateOneTimeCode)($digits);

        return DB::transaction(function () use ($email, $code, $expires, $now, $ip, $userAgent, $rateLimitKey) {
            $challenge = LoginChallenge::create([
                'email' => $email,
                'code_hash' => Hash::make($code),
                'expires_at' => $now->addMinutes($expires),
                'requested_ip' => $ip,
                'requested_user_agent' => $userAgent ? Str::limit($userAgent, 512, '') : null,
            ]);

            RateLimiter::hit($rateLimitKey, (int) CarbonInterval::hour()->totalSeconds);

            Mail::to($email)->queue(
                new LoginVerificationCodeNotification(
                    code: $code,
                    expiresAt: $challenge->expires_at,
                )->afterCommit()
            );

            $challenge->update(['email_sent_at' => $now]);

            return $challenge;
        });
    }
}
