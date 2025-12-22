<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Local;

use App\Domains\Core\ValueObjects\LoginCodeSession;
use App\Domains\User\Models\LoginChallenge;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ShowLoginCodeFormController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        abort_unless(config('auth.local.enabled'), 404);

        $email = session(LoginCodeSession::EMAIL);

        if (! $email) {
            return redirect()->route('login-code.request');
        }

        $challengeId = session(LoginCodeSession::CHALLENGE_ID);
        $challenge = $challengeId ? LoginChallenge::find($challengeId) : null;

        if ($challenge && ($challenge->isConsumed() || $challenge->isExpired())) {
            session()->forget(LoginCodeSession::CHALLENGE_ID);
            $challenge = null;
        }

        return view('auth.login-code', [
            'email' => $email,
            'resendAvailableAt' => (int) (session(LoginCodeSession::RESEND_AVAILABLE_AT) ?? 0),
        ]);
    }
}
