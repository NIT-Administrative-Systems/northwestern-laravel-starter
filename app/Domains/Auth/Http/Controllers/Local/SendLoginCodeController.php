<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers\Local;

use App\Domains\Auth\Actions\Local\IssueLoginChallenge;
use App\Domains\Auth\Http\Requests\SendLoginCodeRequest;
use App\Domains\Auth\Models\LoginChallenge;
use App\Domains\Auth\ValueObjects\LoginCodeSession;
use App\Domains\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Handles both initial login code requests and resend requests with timing protection.
 *
 * This controller uses a best-effort timing equalization strategy to reduce the risk of user
 * enumeration via response-time differences. If an attacker can reliably distinguish
 * between the "user exists" path and the "user does not exist" path based on how
 * quickly the request returns, they can build a list of valid email addresses.
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
     * Send initial login code.
     */
    public function send(SendLoginCodeRequest $request): RedirectResponse
    {
        abort_unless(config('auth.local.enabled'), 404);

        $email = $request->email();
        $challenge = $this->processLoginCodeRequest($email, $request);

        session([
            LoginCodeSession::EMAIL => $email,
            LoginCodeSession::CHALLENGE_ID => $challenge
                ? Crypt::encryptString((string) $challenge->id)
                : Crypt::encryptString(Str::uuid()->toString()),
        ]);

        return to_route('login-code.code');
    }

    /**
     * Resend login code.
     */
    public function resend(Request $request): RedirectResponse
    {
        abort_unless(config('auth.local.enabled'), 404);

        $email = session(LoginCodeSession::EMAIL);

        if (! $email) {
            return redirect()->route('login-code.request');
        }

        $cooldownKey = "login-code-resend:{$email}";
        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            $seconds = RateLimiter::availableIn($cooldownKey);

            return back()->with('status', "Please wait {$seconds} seconds before requesting another code.");
        }

        $challenge = $this->processLoginCodeRequest($email, $request);

        session([
            LoginCodeSession::CHALLENGE_ID => $challenge
                ? Crypt::encryptString((string) $challenge->id)
                : session(LoginCodeSession::CHALLENGE_ID),
        ]);

        RateLimiter::hit(
            $cooldownKey,
            (int) config('auth.local.code.resend_cooldown_seconds', 30)
        );

        return back()->with('status', 'Verification code resent.');
    }

    /**
     * Process login code request with timing protection.
     *
     * This method enforces a minimum response time with random jitter to prevent
     * timing-based user enumeration attacks. It attempts to issue a login challenge
     * for valid users while maintaining consistent timing for both valid and invalid
     * email addresses.
     *
     * @throws ValidationException If rate limit exceeded
     */
    private function processLoginCodeRequest(string $email, Request $request): ?LoginChallenge
    {
        $jitterMs = random_int(0, 50);
        $minimumTimeMs = self::MIN_TOTAL_RESPONSE_TIME_MS + $jitterMs;
        $challenge = null;
        $error = null;

        $this->timebox->call(function (Timebox $timebox) use ($email, $request, &$challenge, &$error) {
            $timebox->dontReturnEarly();
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
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }, $minimumTimeMs * 1000);

        if ($error) {
            throw ValidationException::withMessages(['email' => $error]);
        }

        return $challenge;
    }
}
