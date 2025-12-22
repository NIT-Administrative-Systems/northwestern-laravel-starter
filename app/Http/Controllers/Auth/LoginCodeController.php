<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domains\User\Actions\DetermineUserSegment;
use App\Domains\User\Actions\Local\IssueLoginChallenge;
use App\Domains\User\Actions\Local\VerifyLoginChallengeCode;
use App\Domains\User\Models\LoginChallenge;
use App\Domains\User\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendLoginLinkRequest;
use Carbon\CarbonInterval;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class LoginCodeController extends Controller
{
    /**
     * Minimum total time (in milliseconds) the {@see sendCode} method should take to execute.
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
        private readonly IssueLoginChallenge $issueLoginChallenge,
        private readonly VerifyLoginChallengeCode $verifyLoginChallengeCode,
        private readonly DetermineUserSegment $determineUserSegment,
        private readonly Timebox $timebox,
    ) {
        //
    }

    /**
     * Show the login code request form.
     */
    public function showRequestForm(): View
    {
        abort_unless(config('auth.local.enabled'), 404);

        return view('auth.login-code-request');
    }

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
    public function sendCode(SendLoginLinkRequest $request): RedirectResponse
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

        if ($challenge !== null) {
            session([
                'login_code.email' => $email,
                'login_code.challenge_id' => (string) $challenge->id,
                'login_code.resend_available_at' => now()->addSeconds(
                    (int) config('auth.local.code.resend_cooldown_seconds', 30)
                )->timestamp,
            ]);
        }

        return to_route('login-code.code');
    }

    public function showCodeForm(): View|RedirectResponse
    {
        abort_unless(config('auth.local.enabled'), 404);

        $email = session('login_code.email');

        if (! $email) {
            return redirect()->route('login-code.request');
        }

        $challengeId = session('login_code.challenge_id');
        $challenge = $challengeId ? LoginChallenge::find($challengeId) : null;

        if ($challenge && ($challenge->isConsumed() || $challenge->isExpired())) {
            session()->forget('login_code.challenge_id');
            $challenge = null;
        }

        return view('auth.login-code', [
            'email' => $email,
            'resendAvailableAt' => (int) (session('login_code.resend_available_at') ?? 0),
        ]);
    }

    public function verifyCode(Request $request): RedirectResponse
    {
        abort_unless(config('auth.local.enabled'), 404);

        $digits = (int) config('auth.local.code.digits', 6);
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:' . $digits],
        ]);

        $challengeId = session('login_code.challenge_id');

        if (! $challengeId) {
            return back()->withErrors(['code' => 'Invalid code.'])->onlyInput('code');
        }

        $challenge = LoginChallenge::find($challengeId);

        if (! $challenge) {
            return back()->withErrors(['code' => 'Invalid code.'])->onlyInput('code');
        }

        if ($challenge->isLocked()) {
            return back()->withErrors([
                'code' => 'Too many attempts. Try again later.',
            ])->onlyInput('code');
        }

        $codeVerified = ($this->verifyLoginChallengeCode)(
            $challenge,
            $validated['code'],
            $request->ip(),
            $request->userAgent()
        );

        if (! $codeVerified) {
            return back()->withErrors(['code' => 'Invalid code.'])->onlyInput('code');
        }

        $user = User::firstLocalByEmail($challenge->email);

        if (! $user) {
            return back()->withErrors(['code' => 'Invalid code.'])->onlyInput('code');
        }

        DB::transaction(static function () use ($user, $request) {
            if (! $user->email_verified_at) {
                $user->update(['email_verified_at' => now()]);
            }

            Auth::login($user, remember: true);
            $request->session()->regenerate();
        });

        $user->login_records()->create([
            'logged_in_at' => now(),
            'segment' => ($this->determineUserSegment)($user),
        ]);

        $request->session()->forget(['login_code.email', 'login_code.challenge_id', 'login_code.resend_available_at']);

        return redirect()->intended(config('auth.local.redirect_after_login'));
    }

    public function resendCode(Request $request): RedirectResponse
    {
        abort_unless(config('auth.local.enabled'), 404);

        $email = session('login_code.email');

        if (! $email) {
            return redirect()->route('login-code.request');
        }

        $resendAvailableAt = (int) (session('login_code.resend_available_at') ?? 0);
        if (time() < $resendAvailableAt) {
            return back()->with('status', 'Please wait before requesting another code.');
        }

        $user = User::firstLocalByEmail($email);
        if (! $user) {
            session([
                'login_code.resend_available_at' => now()->addSeconds(
                    (int) config('auth.local.code.resend_cooldown_seconds', 30)
                )->timestamp,
            ]);

            return back();
        }

        try {
            $challenge = ($this->issueLoginChallenge)(
                $email,
                $request->ip(),
                $request->userAgent()
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }

        session([
            'login_code.challenge_id' => (string) $challenge->id,
            'login_code.resend_available_at' => now()->addSeconds(
                (int) config('auth.local.code.resend_cooldown_seconds', 30)
            )->timestamp,
        ]);

        return back()->with('status', 'Verification code resent.');
    }

    /**
     * Check session-based rate limiting to prevent form spam.
     *
     * This protects against attackers spamming the form with non-existent emails from a
     * single browser session, which would otherwise bypass the per-email rate limiting
     * enforced inside {@see IssueLoginChallenge}. Rate limiting constrains request volume,
     * while the timing equalization in {@see sendCode} helps limit what can be
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
