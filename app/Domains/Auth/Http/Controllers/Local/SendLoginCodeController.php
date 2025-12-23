<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers\Local;

use App\Domains\Auth\Actions\Local\IssueLoginChallenge;
use App\Domains\Auth\Http\Requests\SendLoginCodeRequest;
use App\Domains\Auth\ValueObjects\LoginCodeSession;
use App\Domains\User\Models\User;
use App\Http\Controllers\Controller;
use Carbon\CarbonInterval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Attempt to send a login code while enforcing a minimum response time.
 *
 * This method uses a best-effort timing equalization strategy to reduce the risk of
 * user enumeration via response-time differences. If an attacker can reliably
 * distinguish between the "user exists" path and the "user does not exist"
 * path based on how quickly the request returns, they can gradually
 * build a list of valid email addresses for this application.
 *
 * In many contexts, the mere fact that an email address is registered here can be
 * sensitive information.
 */
class SendLoginCodeController extends Controller
{
    /**
     * Minimum total time (in milliseconds) the route should take to execute.
     *
     * This is one layer of defense against time-based user enumeration attacks. By enforcing
     * a floor on the response time, we reduce the timing gap between the "user exists" and
     * "user does not exist" paths, making it harder for an attacker to infer whether a
     * particular email address is registered based solely on response time.
     *
     * This complements rate limiting, but does not replace it. Rate limiting can slow
     * down abuse from a single client or IP, yet a determined attacker can still
     * distribute requests across many sessions, machine, or proxies and perform
     * analysis on response times.
     */
    private const int MIN_TOTAL_RESPONSE_TIME_MS = 300;

    public function __construct(
        private readonly Timebox $timebox,
        private readonly IssueLoginChallenge $issueLoginChallenge,
    ) {
        //
    }

    public function __invoke(SendLoginCodeRequest $request): RedirectResponse
    {
        abort_unless(config('auth.local.enabled'), 404);

        $this->checkSessionRateLimit($request);

        $email = $request->email();
        $jitterMs = random_int(0, 50);
        $minimumTimeMs = self::MIN_TOTAL_RESPONSE_TIME_MS + $jitterMs;
        $challenge = null;

        $this->timebox->call(function (Timebox $timebox) use ($email, $request, &$challenge) {
            $user = User::firstLocalByEmail($email);

            if (! $user) {
                return;
            }

            try {
                $challenge = ($this->issueLoginChallenge)(
                    $email,
                    $request->ip(),
                    $request->userAgent()
                );
                $timebox->returnEarly();
            } catch (RuntimeException $e) {
                // Rate limit exceeded
                throw ValidationException::withMessages([
                    'email' => $e->getMessage(),
                ]);
            }
        }, $minimumTimeMs * 1000);

        $challengeId = $challenge ? (string) $challenge->id : Str::uuid()->toString();
        $encryptedId = Crypt::encryptString($challengeId);

        session([
            LoginCodeSession::EMAIL => $email,
            LoginCodeSession::CHALLENGE_ID => $encryptedId,
            LoginCodeSession::RESEND_AVAILABLE_AT => now()->addSeconds(
                (int) config('auth.local.code.resend_cooldown_seconds', 30)
            )->timestamp,
        ]);

        return to_route('login-code.code');
    }

    /**
     * Check session-based rate limiting to prevent form spam.
     *
     * This protects against attackers spamming the form with non-existent emails from a
     * single browser session, which would otherwise bypass the per-email rate limiting
     * enforced inside {@see IssueLoginChallenge}. Rate limiting constrains request volume,
     * while the timing equalization in the route method helps limit what can be
     * learned from response times as a side-channel.
     *
     * @throws ValidationException
     *
     * @codeCoverageIgnore
     */
    private function checkSessionRateLimit(Request $request): void
    {
        $sessionId = $request->session()->getId();
        $rateLimitKey = "login-code-form:{$sessionId}";
        $maxAttempts = (int) config('auth.local.rate_limit_per_hour');

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $minutes = (int) ceil($seconds / 60);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$minutes} minute(s).",
            ]);
        }

        RateLimiter::hit($rateLimitKey, (int) CarbonInterval::hour()->totalSeconds);
    }
}
