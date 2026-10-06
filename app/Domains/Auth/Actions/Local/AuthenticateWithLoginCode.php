<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Local;

use App\Domains\Auth\Models\LoginChallenge;
use App\Domains\User\Actions\RecordLogin;
use App\Domains\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use Northwestern\SysDev\Chassis\Formatting\CountInWords;

/**
 * Verifies a login code against its challenge and resolves the local user it
 * signs in. Recording the login and marking the email verified happen here;
 * starting the session is left to the caller.
 *
 * Like {@see RequestLoginCode}, verification takes the same time whether or not the
 * challenge is real: an unregistered email gets a decoy challenge ID, and without a
 * floor the missing bcrypt check would reveal that.
 */
class AuthenticateWithLoginCode
{
    /** Minimum time (in milliseconds) to resolve a challenge, above a bcrypt check at production cost. */
    private const int MIN_RESOLVE_TIME_MS = 500;

    public function __construct(
        private readonly Timebox $timebox,
        private readonly VerifyLoginChallengeCode $verifyLoginChallengeCode,
        private readonly RecordLogin $recordLogin,
    ) {
        //
    }

    /**
     * @param  ?string  $challengeId  The decrypted challenge ID from the session. Non-numeric IDs are decoys.
     *
     * @throws ValidationException Keyed by `code` when the code is rejected or a limit is exceeded.
     */
    public function __invoke(?string $challengeId, string $code, Request $request): User
    {
        $this->enforceRateLimits($challengeId, $request);

        if ($challengeId === null) {
            throw ValidationException::withMessages(['code' => 'That code didn\'t work. Check it and try again.']);
        }

        $minimumTimeMs = self::MIN_RESOLVE_TIME_MS + random_int(0, 50);

        $challenge = $this->timebox->call(function (Timebox $timebox) use ($challengeId, $code, $request): LoginChallenge|ValidationException {
            $timebox->dontReturnEarly();

            // The rejection is returned, not thrown, so the transaction commits the failed attempt and any lockout.
            return DB::transaction(fn () => $this->resolveChallenge($challengeId, $code, $request));
        }, $minimumTimeMs * 1000);

        if ($challenge instanceof ValidationException) {
            throw $challenge;
        }

        return $this->authenticateUser($challenge, $request);
    }

    /**
     * Limit verification attempts per IP and per challenge, counting every attempt.
     *
     * @throws ValidationException
     */
    private function enforceRateLimits(?string $challengeId, Request $request): void
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
     * Find the challenge, check lockout, and verify the code.
     *
     * Must run inside a transaction with `lockForUpdate` to prevent race conditions. Returns the
     * rejection rather than throwing it: a throw would roll back the attempt count.
     */
    private function resolveChallenge(string $challengeId, string $code, Request $request): LoginChallenge|ValidationException
    {
        // Non-numeric IDs are decoy values stored for non-existent users to prevent timing enumeration.
        $challenge = ctype_digit($challengeId)
            ? LoginChallenge::query()->lockForUpdate()->find($challengeId)
            : null;

        if (! $challenge) {
            return ValidationException::withMessages(['code' => 'That code didn\'t work. Check it and try again.']);
        }

        if ($challenge->isLocked()) {
            $lockoutMinutes = (int) config('local-auth.code.lock_minutes', 15);

            return ValidationException::withMessages([
                'code' => 'Too many attempts. Try again in ' . CountInWords::of($lockoutMinutes, 'minute') . '.',
            ]);
        }

        $codeVerified = ($this->verifyLoginChallengeCode)(
            $challenge,
            $code,
            $request->ip(),
            $request->userAgent()
        );

        if (! $codeVerified) {
            return ValidationException::withMessages(['code' => 'That code didn\'t work. Check it and try again.']);
        }

        return $challenge;
    }

    /**
     * Resolve the user from the challenge, verify their email, and record the login.
     *
     * @throws ValidationException
     */
    private function authenticateUser(LoginChallenge $challenge, Request $request): User
    {
        $user = User::firstLocalByEmail($challenge->email);

        if (! $user) {
            throw ValidationException::withMessages(['code' => 'That code didn\'t work. Check it and try again.']);
        }

        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        ($this->recordLogin)($user, $request);

        return $user;
    }
}
