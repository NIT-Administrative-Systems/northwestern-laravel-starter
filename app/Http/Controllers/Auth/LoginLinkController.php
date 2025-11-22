<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domains\User\Actions\DetermineUserSegment;
use App\Domains\User\Actions\Local\SendLoginLink;
use App\Domains\User\Actions\Local\ValidateLoginLink;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendLoginLinkRequest;
use Carbon\CarbonInterval;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use SensitiveParameter;

class LoginLinkController extends Controller
{
    /**
     * Minimum total time (in milliseconds) the {@see sendLink} method should take to execute.
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
        private readonly SendLoginLink $sendLoginLink,
        private readonly ValidateLoginLink $validateLoginLink,
        private readonly DetermineUserSegment $determineUserSegment,
    ) {
        //
    }

    /**
     * Show the login link request form.
     */
    public function showRequestForm(): View
    {
        abort_unless(config('auth.local.enabled'), 404);

        return view('auth.login-link-request');
    }

    /**
     * Attempt to send a login link while enforcing a minimum response time.
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
    public function sendLink(SendLoginLinkRequest $request): RedirectResponse
    {
        abort_unless(config('auth.local.enabled'), 404);

        $this->checkSessionRateLimit($request);

        $email = $request->email();
        $start = microtime(true);

        $user = User::firstLocalByEmail($email);

        if ($user) {
            try {
                ($this->sendLoginLink)($user, $request->ip());
            } catch (RuntimeException $e) {
                // Rate limit exceeded
                throw ValidationException::withMessages([
                    'email' => $e->getMessage(),
                ]);
            }
        }

        $elapsedMs = (int) ((microtime(true) - $start) * 1000);

        $jitterMs = random_int(0, 50);
        $targetMs = self::MIN_TOTAL_RESPONSE_TIME_MS + $jitterMs;

        if ($elapsedMs < $targetMs) {
            Sleep::for($targetMs - $elapsedMs)->milliseconds();
        }

        return back()->with('status', 'If an account with that email exists, a login link has been sent.');
    }

    public function verify(
        Request $request,
        #[SensitiveParameter]
        string $token
    ): RedirectResponse {
        abort_unless(config('auth.local.enabled'), 404);

        $user = ($this->validateLoginLink)($token);

        if (! $user) {
            return redirect()
                ->route('login-link.request')
                ->with([
                    'status-danger' => 'This login link is invalid or has expired. Please request a new one.',
                ]);
        }

        $hashedToken = UserLoginLink::hashFromPlain($token);

        $loginLink = UserLoginLink::where('token', $hashedToken)->firstOrFail();
        $loginLink->markAsUsed($request->ip());

        if (! $user->email_verified_at) {
            $user->update(['email_verified_at' => now()]);
        }

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        $user->login_records()->create([
            'logged_in_at' => now(),
            'segment' => ($this->determineUserSegment)($user),
        ]);

        return redirect()->intended(config('auth.local.redirect_after_login'));
    }

    /**
     * Check session-based rate limiting to prevent form spam.
     *
     * This protects against attackers spamming the form with non-existent emails from a
     * single browser session, which would otherwise bypass the per-email rate limiting
     * enforced inside {@see SendLoginLink}. Rate limiting constrains request volume,
     * while the timing equalization in {@see sendLink} helps limit what can be
     * learned from response times as a side-channel.
     *
     * @throws ValidationException
     *
     * @codeCoverageIgnore
     */
    private function checkSessionRateLimit(Request $request): void
    {
        $sessionId = $request->session()->getId();
        $rateLimitKey = "login-link-form:{$sessionId}";
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
