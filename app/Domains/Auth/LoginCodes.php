<?php

declare(strict_types=1);

namespace App\Domains\Auth;

use App\Domains\Auth\Contracts\OneTimeCodeGenerator;
use App\Domains\Auth\Jobs\SendLoginCodeEmailJob;
use App\Domains\Auth\Models\LoginChallenge;
use App\Domains\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use Northwestern\SysDev\Chassis\Formatting\CountInWords;

/**
 * Signing in with a code sent by email, for local accounts without a NetID: request a code,
 * then enter it. The page renders the steps; this owns everything behind them.
 *
 * Nothing it does reveals whether an email has an account. An unknown email takes the same
 * time, consumes the same limits, gets the same errors and moves to the same code step as a
 * known one, with a decoy in place of a real challenge, and checking a code takes the same time
 * whether or not its challenge is real.
 *
 * Progress lives in the session, with the challenge ID encrypted so real IDs and decoys look
 * alike, and a reload returns to the step the person was on. Every code email links to the code
 * step with its challenge ID encrypted in the URL ({@see link()}), so a code sent by an
 * administrator, or read on another device, can still be entered. The link alone signs no one in.
 *
 * ```php
 * $codes->request($email, $request);         // the email step
 * $user = $codes->verify($code, $request);   // the code step
 * return $signIn->complete($user, $request, SignInMethod::EmailCode);
 * ```
 */
final readonly class LoginCodes
{
    /** The query parameter that carries the encrypted challenge ID in a code email's link. */
    public const string LINK_PARAMETER = 'challenge';

    private const string SESSION_EMAIL = 'login_code.email';

    private const string SESSION_CHALLENGE = 'login_code.challenge_id';

    /**
     * The least time, in milliseconds, requesting a code takes, so an email with an account and
     * one without can't be told apart by response time. Rate limits slow one client down; this
     * covers an attacker who spreads requests over many.
     */
    private const int MIN_REQUEST_TIME_MS = 500;

    /** The least time, in milliseconds, checking a code takes: above a bcrypt check at production cost. */
    private const int MIN_VERIFY_TIME_MS = 500;

    private const int MAX_USER_AGENT_LENGTH = 512;

    private const string REJECTED = 'That code didn\'t work. Check it and try again.';

    public function __construct(
        private Timebox $timebox,
        private OneTimeCodeGenerator $generateCode,
    ) {
        //
    }

    /**
     * Starts signing in with `$email`: sends a code when a local account has that email, and moves
     * to the code step either way.
     *
     * @throws ValidationException Keyed by `email` when a limit is reached
     */
    public function request(string $email, Request $request): void
    {
        $email = $this->normalize($email);
        $challenge = $this->requestChallenge($email, $request);

        Session::put([
            self::SESSION_EMAIL => $email,
            self::SESSION_CHALLENGE => $this->encrypt($challenge),
        ]);
    }

    /**
     * Seconds until another code can be sent for the pending email, or 0 when it can be sent now.
     */
    public function resendAvailableIn(): int
    {
        $email = $this->pendingEmail();

        if ($email === null || ! RateLimiter::tooManyAttempts($this->resendKey($email), 1)) {
            return 0;
        }

        return RateLimiter::availableIn($this->resendKey($email));
    }

    /**
     * Sends another code for the pending email, which replaces the one to enter. An email without
     * a local account keeps its decoy. Does nothing when no sign-in is pending.
     *
     * @throws ValidationException Keyed by `email` while the resend cooldown runs or when a limit is reached
     */
    public function resend(Request $request): void
    {
        $email = $this->pendingEmail();

        if ($email === null) {
            return;
        }

        if (($seconds = $this->resendAvailableIn()) > 0) {
            throw ValidationException::withMessages(['email' => 'You can request another code in ' . CountInWords::of($seconds, 'second') . '.']);
        }

        $challenge = $this->requestChallenge($email, $request);

        if ($challenge instanceof LoginChallenge) {
            Session::put(self::SESSION_CHALLENGE, $this->encrypt($challenge));
        }

        RateLimiter::hit($this->resendKey($email), (int) config('local-auth.code.resend_cooldown_seconds', 30));
    }

    /**
     * Checks a code for the pending sign-in, ends it, and returns the person the code signs in,
     * with their email marked verified. The caller finishes signing them in.
     *
     * @throws ValidationException Keyed by `code` when the code is rejected or a limit is reached
     */
    public function verify(string $code, Request $request): User
    {
        $challengeId = $this->pendingChallengeId();
        $this->enforceVerifyLimits($challengeId, $request);

        if ($challengeId === null) {
            throw ValidationException::withMessages(['code' => self::REJECTED]);
        }

        $minimumTimeMs = self::MIN_VERIFY_TIME_MS + random_int(0, 50);
        $challenge = $this->timebox->call(function (Timebox $timebox) use ($challengeId, $code, $request): LoginChallenge|ValidationException {
            $timebox->dontReturnEarly();

            // The rejection is returned, not thrown, so the transaction commits the failed attempt and any lockout.
            return DB::transaction(fn (): LoginChallenge|ValidationException => $this->consume($challengeId, $code, $request));
        }, $minimumTimeMs * 1000);

        if ($challenge instanceof ValidationException) {
            throw $challenge;
        }

        $user = User::firstLocalByEmail($challenge->email) ?? throw ValidationException::withMessages(['code' => self::REJECTED]);

        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $this->cancel();

        return $user;
    }

    /**
     * Sends `$user` a code an administrator asked for. It's recorded as the administrator's request
     * and counts against the person's hourly limit, so nobody can flood their inbox.
     *
     * @throws ValidationException Keyed by `email` when the person's hourly limit is reached
     */
    public function issueFor(User $user, Request $request): LoginChallenge
    {
        $email = $this->normalize((string) $user->email);
        $key = "login-code:{$email}";

        if (RateLimiter::tooManyAttempts($key, (int) config('local-auth.rate_limit_per_hour'))) {
            throw ValidationException::withMessages(['email' => $this->tooManySignInAttempts($key)]);
        }

        return $this->issue($email, $request);
    }

    /**
     * The code step's address for a challenge, for its email.
     */
    public function link(LoginChallenge $challenge): string
    {
        return route('filament.app.auth.login-code', [self::LINK_PARAMETER => $this->encrypt($challenge)]);
    }

    /**
     * Moves to the code step from an email's link. A token that can't be decrypted, or a challenge
     * that's expired, used or locked, is ignored.
     */
    public function startFromLink(string $token): void
    {
        try {
            $challengeId = Crypt::decryptString($token);
        } catch (DecryptException) {
            return;
        }

        $challenge = ctype_digit($challengeId) ? LoginChallenge::query()->find($challengeId) : null;

        if ($challenge?->isActive()) {
            Session::put([
                self::SESSION_EMAIL => $challenge->email,
                self::SESSION_CHALLENGE => $this->encrypt($challenge),
            ]);
        }
    }

    /**
     * The email a code was requested for, while a sign-in is pending.
     */
    public function pendingEmail(): ?string
    {
        $email = Session::get(self::SESSION_EMAIL);

        return is_string($email) && $email !== '' ? $email : null;
    }

    public function cancel(): void
    {
        Session::forget([self::SESSION_EMAIL, self::SESSION_CHALLENGE]);
    }

    /**
     * Checks every limit before looking the email up, then issues a challenge only when a local
     * account has it, all inside the same minimum time.
     *
     * @throws ValidationException
     */
    private function requestChallenge(string $email, Request $request): ?LoginChallenge
    {
        $challenge = null;
        $error = null;
        $minimumTimeMs = self::MIN_REQUEST_TIME_MS + random_int(0, 50);

        $this->timebox->call(function (Timebox $timebox) use ($email, $request, &$challenge, &$error): void {
            $timebox->dontReturnEarly();

            $error = $this->requestLimitError($email, $request->ip() ?? 'ip:none');

            if ($error !== null) {
                return;
            }

            if (User::firstLocalByEmail($email) instanceof User) {
                $challenge = $this->issue($email, $request);
            } else {
                // An unknown email consumes its hourly slot too, so probing it is throttled the same way.
                RateLimiter::hit("login-code:{$email}", 3600);
            }
        }, $minimumTimeMs * 1000);

        if ($error !== null) {
            throw ValidationException::withMessages(['email' => $error]);
        }

        return $challenge;
    }

    /**
     * Per minute, per IP and per email; per hour, per IP, so one address can't use up many
     * accounts' limits and lock them out, and per email. Every limit is checked before any is
     * counted, so a known and an unknown email are refused alike.
     */
    private function requestLimitError(string $email, string $ip): ?string
    {
        $perMinute = [
            "login-code:req:ip:{$ip}" => (int) config('rate-limiting.auth.login_code.request.per_minute'),
            "login-code:req:{$email}" => (int) config('rate-limiting.auth.login_code.request.per_email_per_minute'),
        ];

        foreach ($perMinute as $key => $maxAttempts) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                return 'Too many requests. Try again in ' . CountInWords::of(RateLimiter::availableIn($key), 'second') . '.';
            }
        }

        $perHour = [
            "login-code-ip:{$ip}" => (int) config('local-auth.rate_limit_per_ip_per_hour', 20),
            "login-code:{$email}" => (int) config('local-auth.rate_limit_per_hour'),
        ];

        foreach ($perHour as $key => $maxAttempts) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                return $this->tooManySignInAttempts($key);
            }
        }

        foreach (array_keys($perMinute) as $key) {
            RateLimiter::hit($key, 60);
        }

        RateLimiter::hit("login-code-ip:{$ip}", 3600);

        return null;
    }

    /**
     * Creates a challenge with only its code's hash, counts it against the email's hourly limit,
     * and queues the email once the challenge is committed.
     */
    private function issue(string $email, Request $request): LoginChallenge
    {
        return DB::transaction(function () use ($email, $request): LoginChallenge {
            $code = ($this->generateCode)((int) config('local-auth.code.digits', 6));
            $userAgent = $request->userAgent();

            $challenge = LoginChallenge::create([
                'email' => $email,
                'code_hash' => Hash::make($code),
                'expires_at' => CarbonImmutable::now()->addMinutes((int) config('local-auth.code.expires_in_minutes', 10)),
                'requested_ip' => $request->ip(),
                'requested_user_agent' => $userAgent !== null ? Str::limit($userAgent, self::MAX_USER_AGENT_LENGTH, '') : null,
            ]);

            RateLimiter::hit("login-code:{$email}", 3600);

            SendLoginCodeEmailJob::dispatch(
                loginChallengeId: $challenge->id,
                encryptedCode: Crypt::encryptString($code),
            )->afterCommit();

            return $challenge;
        });
    }

    /**
     * Verification attempts per minute, per IP and per challenge, counting every attempt.
     *
     * @throws ValidationException
     */
    private function enforceVerifyLimits(?string $challengeId, Request $request): void
    {
        $ip = $request->ip() ?? 'ip:none';
        $limits = [
            "login-code:verify:{$ip}" => (int) config('rate-limiting.auth.login_code.verify.per_minute'),
            'login-code:verify:challenge:' . ($challengeId ?? $ip) => (int) config('rate-limiting.auth.login_code.verify.per_challenge_per_minute'),
        ];

        foreach ($limits as $key => $maxAttempts) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                throw ValidationException::withMessages([
                    'code' => 'Too many attempts. Try again in ' . CountInWords::of(RateLimiter::availableIn($key), 'second') . '.',
                ]);
            }
        }

        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key, 60);
        }
    }

    /**
     * Finds the challenge, locked for update, and uses it up if the code matches. A wrong code
     * counts an attempt, and enough of them lock the challenge. Must run in a transaction, and
     * returns the rejection rather than throwing it, which would roll the attempt back.
     */
    private function consume(string $challengeId, string $code, Request $request): LoginChallenge|ValidationException
    {
        // A non-numeric ID is the decoy stored for an email without an account.
        $challenge = ctype_digit($challengeId) ? LoginChallenge::query()->lockForUpdate()->find($challengeId) : null;

        if (! $challenge instanceof LoginChallenge) {
            return ValidationException::withMessages(['code' => self::REJECTED]);
        }

        if ($challenge->isLocked()) {
            return ValidationException::withMessages([
                'code' => 'Too many attempts. Try again in ' . CountInWords::of((int) config('local-auth.code.lock_minutes', 15), 'minute') . '.',
            ]);
        }

        $now = CarbonImmutable::now();

        if (! $challenge->isActive($now)) {
            return ValidationException::withMessages(['code' => self::REJECTED]);
        }

        if (! Hash::check($code, $challenge->code_hash)) {
            $challenge->increment('attempts');

            if ($challenge->attempts >= (int) config('local-auth.code.max_attempts', 8)) {
                $challenge->update(['locked_until' => $now->addMinutes((int) config('local-auth.code.lock_minutes', 15))]);
            }

            return ValidationException::withMessages(['code' => self::REJECTED]);
        }

        $userAgent = $request->userAgent();
        $challenge->update([
            'consumed_at' => $now,
            'consumed_ip' => $request->ip(),
            'consumed_user_agent' => $userAgent !== null ? Str::limit($userAgent, self::MAX_USER_AGENT_LENGTH, '') : null,
        ]);

        return $challenge;
    }

    /**
     * The pending challenge's ID: numeric for a real challenge, a UUID for a decoy. A value that
     * can't be decrypted is dropped.
     */
    private function pendingChallengeId(): ?string
    {
        $encrypted = Session::get(self::SESSION_CHALLENGE);

        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            Session::forget(self::SESSION_CHALLENGE);

            return null;
        }
    }

    private function tooManySignInAttempts(string $key): string
    {
        $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

        return 'Too many sign-in attempts. Try again in ' . CountInWords::of($minutes, 'minute') . '.';
    }

    private function resendKey(string $email): string
    {
        return "login-code-resend:{$email}";
    }

    private function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * A challenge's ID, encrypted, or a decoy's: a random UUID, which looks the same once encrypted.
     */
    private function encrypt(?LoginChallenge $challenge): string
    {
        return Crypt::encryptString($challenge instanceof LoginChallenge ? (string) $challenge->id : Str::uuid()->toString());
    }
}
