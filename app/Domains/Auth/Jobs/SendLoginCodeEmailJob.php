<?php

declare(strict_types=1);

namespace App\Domains\Auth\Jobs;

use App\Domains\Auth\Mail\LoginCodeNotification;
use App\Domains\Auth\Models\LoginChallenge;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Responsible for sending the {@see LoginCodeNotification} email containing the OTP, and recording
 * the {@see LoginChallenge::$email_sent_at} timestamp.
 */
class SendLoginCodeEmailJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $encryptedCode  The verification code encrypted at the time the challenge is issued.
     *
     * Queued jobs are serialized and transported through the queue (e.g., SES), and the payload
     * commonly becomes visible in multiple places:
     * - The queue transport itself
     * - Worker logs and retry/failure payloads
     * - APM tools that capture job context
     *
     * While the verification code is short-lived, it is still a valid authentication factor during its
     * lifetime. Encrypting it ensures the raw code is not exposed in any of these places.
     */
    public function __construct(
        public readonly int $loginChallengeId,
        public readonly string $encryptedCode,
    ) {
        //
    }

    public function handle(): void
    {
        $challenge = LoginChallenge::find($this->loginChallengeId);

        if (! $challenge) {
            return;
        }

        if (filled($challenge->email_sent_at)) {
            return;
        }

        Mail::to($challenge->email)->send(
            new LoginCodeNotification(
                encryptedCode: $this->encryptedCode,
                expiresAt: $challenge->expires_at,
            )
        );

        $challenge->update(['email_sent_at' => CarbonImmutable::now()]);
    }
}
