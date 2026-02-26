<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers\Local;

use App\Domains\Auth\Actions\Local\VerifyLoginChallengeCode;
use App\Domains\Auth\Models\LoginChallenge;
use App\Domains\Auth\ValueObjects\LoginCodeSession;
use App\Domains\User\Actions\RecordLogin;
use App\Domains\User\Models\User;
use App\Http\Controllers\Controller;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class VerifyLoginCodeController extends Controller
{
    public function __construct(
        private readonly VerifyLoginChallengeCode $verifyLoginChallengeCode,
        private readonly RecordLogin $recordLogin,
    ) {
        //
    }

    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(config('local-auth.enabled'), 404);

        $digits = (int) config('local-auth.code.digits', 6);
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:' . $digits],
        ]);

        $challengeId = $this->decryptChallengeId();

        if (! $challengeId) {
            return back()->withErrors(['code' => 'Invalid code.'])->onlyInput('code');
        }

        try {
            $challenge = DB::transaction(fn () => $this->resolveChallenge($challengeId, $validated['code'], $request));
            $user = $this->authenticateUser($challenge, $request);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->onlyInput('code');
        }

        Auth::login($user, remember: true);
        Session::regenerate();
        Session::regenerateToken();
        Session::forget(LoginCodeSession::KEYS);

        return redirect()->intended(config('local-auth.redirect_after_login'));
    }

    /**
     * Decrypt the challenge ID from the session, returning null if missing or tampered.
     */
    private function decryptChallengeId(): ?string
    {
        $encrypted = session(LoginCodeSession::CHALLENGE_ID);

        if (! $encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            Session::forget(LoginCodeSession::CHALLENGE_ID);

            return null;
        }
    }

    /**
     * Find the challenge, check lockout, and verify the code.
     *
     * Must run inside a transaction with `lockForUpdate` to prevent race conditions.
     *
     * @throws ValidationException
     */
    private function resolveChallenge(string $challengeId, string $code, Request $request): LoginChallenge
    {
        // Non-numeric IDs are decoy values stored for non-existent users to prevent timing enumeration.
        $challenge = ctype_digit($challengeId)
            ? LoginChallenge::query()->lockForUpdate()->find($challengeId)
            : null;

        if (! $challenge) {
            throw ValidationException::withMessages(['code' => 'Invalid code.']);
        }

        if ($challenge->isLocked()) {
            $lockoutMinutes = (int) config('local-auth.code.lock_minutes', 15);
            $lockoutDuration = CarbonInterval::minutes($lockoutMinutes)->forHumans();

            throw ValidationException::withMessages([
                'code' => "Too many attempts. Please wait {$lockoutDuration} before trying again.",
            ]);
        }

        $codeVerified = ($this->verifyLoginChallengeCode)(
            $challenge,
            $code,
            $request->ip(),
            $request->userAgent()
        );

        if (! $codeVerified) {
            throw ValidationException::withMessages(['code' => 'Invalid code.']);
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
            throw ValidationException::withMessages(['code' => 'Invalid code.']);
        }

        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        ($this->recordLogin)($user, $request);

        return $user;
    }
}
