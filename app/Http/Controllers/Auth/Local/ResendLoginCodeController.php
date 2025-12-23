<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Local;

use App\Domains\Core\ValueObjects\LoginCodeSession;
use App\Domains\User\Actions\Local\IssueLoginChallenge;
use App\Domains\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class ResendLoginCodeController extends Controller
{
    public function __construct(
        private readonly IssueLoginChallenge $issueLoginChallenge,
    ) {
        //
    }

    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(config('auth.local.enabled'), 404);

        $email = session(LoginCodeSession::EMAIL);

        if (! $email) {
            return redirect()->route('login-code.request');
        }

        $resendAvailableAt = (int) (session(LoginCodeSession::RESEND_AVAILABLE_AT) ?? 0);
        if (time() < $resendAvailableAt) {
            return back()->with('status', 'Please wait before requesting another code.');
        }

        $user = User::firstLocalByEmail($email);
        if (! $user) {
            session([
                LoginCodeSession::RESEND_AVAILABLE_AT => now()->addSeconds(
                    (int) config('auth.local.code.resend_cooldown_seconds', 30)
                )->timestamp,
            ]);

            return back()->with('status', 'Verification code resent.');
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
            LoginCodeSession::CHALLENGE_ID => Crypt::encryptString((string) $challenge->id),
            LoginCodeSession::RESEND_AVAILABLE_AT => now()->addSeconds(
                (int) config('auth.local.code.resend_cooldown_seconds', 30)
            )->timestamp,
        ]);

        return back()->with('status', 'Verification code resent.');
    }
}
