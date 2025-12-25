<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers\Local;

use App\Domains\Auth\Actions\Local\IssueLoginChallenge;
use App\Domains\Auth\ValueObjects\LoginCodeSession;
use App\Domains\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Timebox;
use RuntimeException;

/**
 * Resend a login code while enforcing a minimum response time.
 *
 * This method uses the same timing equalization strategy as {@see SendLoginCodeController}
 * to prevent user enumeration via response-time differences. By ensuring both
 * "user exists" and "user does not exist" paths take the same amount of time,
 * we prevent attackers from determining whether an email is registered.
 */
class ResendLoginCodeController extends Controller
{
    /**
     * Minimum total time (in milliseconds) the route should take to execute.
     *
     * This matches {@see SendLoginCodeController} to ensure consistent timing
     * protection across both the initial send and resend endpoints.
     */
    private const int MIN_TOTAL_RESPONSE_TIME_MS = 300;

    public function __construct(
        private readonly Timebox $timebox,
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

        $jitterMs = random_int(0, 50);
        $minimumTimeMs = self::MIN_TOTAL_RESPONSE_TIME_MS + $jitterMs;
        $challenge = null;
        $rateLimitError = null;

        $this->timebox->call(function (Timebox $timebox) use ($email, $request, &$challenge, &$rateLimitError) {
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
                $rateLimitError = $e->getMessage();
            }
        }, $minimumTimeMs * 1000);

        if ($rateLimitError) {
            return back()->withErrors(['email' => $rateLimitError]);
        }

        session([
            LoginCodeSession::CHALLENGE_ID => $challenge
                ? Crypt::encryptString((string) $challenge->id)
                : session(LoginCodeSession::CHALLENGE_ID),
            LoginCodeSession::RESEND_AVAILABLE_AT => now()->addSeconds(
                (int) config('auth.local.code.resend_cooldown_seconds', 30)
            )->timestamp,
        ]);

        return back()->with('status', 'Verification code resent.');
    }
}
