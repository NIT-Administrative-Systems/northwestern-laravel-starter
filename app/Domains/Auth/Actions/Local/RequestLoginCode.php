<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Local;

use App\Domains\Auth\Models\LoginChallenge;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Issues a login code for an email address without revealing whether a local
 * account exists for it.
 *
 * Registered and unregistered emails take the same time, consume the same rate
 * limits, and produce the same errors. Only a registered email gets a challenge.
 */
class RequestLoginCode
{
    /**
     * Minimum total time (in milliseconds) a request should take.
     *
     * This is one layer of defense against time-based user enumeration attacks. By enforcing
     * a floor on the response time, we reduce the timing gap between the "user exists" and
     * "user does not exist" paths, making it harder for an attacker to infer whether a
     * particular email address is registered based solely on response time.
     *
     * This complements rate limiting, but does not replace it. Rate limiting can slow
     * down abuse from a single client or IP, yet a determined attacker can still
     * distribute requests across many sessions, machines, or proxies and perform
     * analysis on response times.
     */
    private const int MIN_TOTAL_RESPONSE_TIME_MS = 500;

    public function __construct(
        private readonly Timebox $timebox,
        private readonly IssueLoginChallenge $issueLoginChallenge,
    ) {
        //
    }

    /**
     * @return ?LoginChallenge The issued challenge, or null when no local user has this email.
     *
     * @throws ValidationException Keyed by `email` when a rate limit is exceeded.
     */
    public function __invoke(string $email, ?string $ip, ?string $userAgent): ?LoginChallenge
    {
        $email = mb_strtolower(trim($email));
        $jitterMs = random_int(0, 50);
        $minimumTimeMs = self::MIN_TOTAL_RESPONSE_TIME_MS + $jitterMs;
        $challenge = null;
        $error = null;

        $this->timebox->call(function (Timebox $timebox) use ($email, $ip, $userAgent, &$challenge, &$error) {
            $timebox->dontReturnEarly();

            $error = $this->rateLimitError($email, $ip);

            if ($error !== null) {
                return;
            }

            $user = User::firstLocalByEmail($email);

            if (! $user) {
                // Consume a rate-limit slot for unknown emails too, so attackers
                // cannot make unlimited probing requests without being throttled.
                RateLimiter::hit("login-code:{$email}", 3600);

                return;
            }

            try {
                $challenge = ($this->issueLoginChallenge)($email, $ip, $userAgent);
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }, $minimumTimeMs * 1000);

        if ($error !== null) {
            throw ValidationException::withMessages(['email' => $error]);
        }

        return $challenge;
    }

    /**
     * Check every limit before the user lookup, so registered and unregistered
     * emails produce identical rate-limit responses.
     */
    private function rateLimitError(string $email, ?string $ip): ?string
    {
        $ip ??= 'ip:none';

        $perMinuteLimits = [
            "login-code:req:ip:{$ip}" => (int) config('rate-limiting.auth.login_code.request.per_minute'),
            "login-code:req:{$email}" => (int) config('rate-limiting.auth.login_code.request.per_email_per_minute'),
        ];

        foreach ($perMinuteLimits as $key => $maxAttempts) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                return 'Too many requests. Please try again in ' . RateLimiter::availableIn($key) . ' seconds.';
            }
        }

        // Per-IP hourly limit: prevents a single IP from exhausting multiple
        // accounts' per-email limits (targeted account lockout DoS).
        $hourlyLimits = [
            "login-code-ip:{$ip}" => (int) config('local-auth.rate_limit_per_ip_per_hour', 20),
            "login-code:{$email}" => (int) config('local-auth.rate_limit_per_hour'),
        ];

        foreach ($hourlyLimits as $key => $maxAttempts) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

                return "Too many login attempts. Please try again in {$minutes} minute(s).";
            }
        }

        foreach (array_keys($perMinuteLimits) as $key) {
            RateLimiter::hit($key, 60);
        }

        RateLimiter::hit("login-code-ip:{$ip}", 3600);

        return null;
    }
}
