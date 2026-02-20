<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers\Local;

use App\Domains\Auth\Models\LoginChallenge;
use App\Domains\Auth\ValueObjects\LoginCodeSession;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;

class ShowLoginCodeFormController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        abort_unless(config('local-auth.enabled'), 404);

        $email = session(LoginCodeSession::EMAIL);

        if (! $email) {
            return redirect()->route('login-code.request');
        }

        $challengeId = null;
        $encryptedId = Session::get(LoginCodeSession::CHALLENGE_ID);

        if ($encryptedId) {
            try {
                $challengeId = Crypt::decryptString($encryptedId);
            } catch (DecryptException) {
                Session::forget(LoginCodeSession::CHALLENGE_ID);
            }
        }

        // Only query if challengeId is numeric. Non-numeric IDs are decoy values
        // stored for non-existent users to prevent timing enumeration.
        $challenge = $challengeId && ctype_digit($challengeId)
            ? LoginChallenge::find($challengeId)
            : null;

        if (! $challenge) {
            Session::forget(LoginCodeSession::CHALLENGE_ID);
        }

        if ($challenge && ($challenge->isConsumed() || $challenge->isExpired())) {
            Session::forget(LoginCodeSession::CHALLENGE_ID);
            $challenge = null;
        }

        $cooldownKey = "login-code-resend:{$email}";
        $resendAvailableAt = RateLimiter::tooManyAttempts($cooldownKey, 1)
            ? now()->addSeconds(RateLimiter::availableIn($cooldownKey))->timestamp
            : 0;

        return view('auth.login-code', [
            'email' => $email,
            'resendAvailableAt' => $resendAvailableAt,
        ]);
    }
}
