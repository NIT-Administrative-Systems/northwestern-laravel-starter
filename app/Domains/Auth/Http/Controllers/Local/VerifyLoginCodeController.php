<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers\Local;

use App\Domains\Auth\Actions\Local\VerifyLoginChallengeCode;
use App\Domains\Auth\Models\LoginChallenge;
use App\Domains\Auth\ValueObjects\LoginCodeSession;
use App\Domains\User\Actions\DetermineUserSegment;
use App\Domains\User\Models\User;
use App\Http\Controllers\Controller;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class VerifyLoginCodeController extends Controller
{
    public function __construct(
        private readonly VerifyLoginChallengeCode $verifyLoginChallengeCode,
        private readonly DetermineUserSegment $determineUserSegment,
    ) {
        //
    }

    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(config('auth.local.enabled'), 404);

        $digits = (int) config('auth.local.code.digits', 6);
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:' . $digits],
        ]);

        $encryptedChallengeId = session(LoginCodeSession::CHALLENGE_ID);

        if (! $encryptedChallengeId) {
            return back()->withErrors(['code' => 'Invalid code.'])->onlyInput('code');
        }

        try {
            $challengeId = Crypt::decryptString($encryptedChallengeId);
        } catch (DecryptException) {
            session()->forget(LoginCodeSession::CHALLENGE_ID);

            return back()->withErrors(['code' => 'Invalid code.'])->onlyInput('code');
        }

        $challenge = LoginChallenge::find($challengeId);

        if (! $challenge) {
            return back()->withErrors(['code' => 'Invalid code.'])->onlyInput('code');
        }

        if ($challenge->isLocked()) {
            $lockoutMinutes = (int) config('auth.local.code.lock_minutes', 15);
            $lockoutDuration = CarbonInterval::minutes($lockoutMinutes)->forHumans();

            return back()->withErrors([
                'code' => "Too many attempts. Please wait {$lockoutDuration} before trying again.",
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

        $request->session()->forget(LoginCodeSession::KEYS);

        return redirect()->intended(config('auth.local.redirect_after_login'));
    }
}
