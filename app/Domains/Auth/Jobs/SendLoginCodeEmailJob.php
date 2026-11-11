<?php

declare(strict_types=1);

namespace App\Domains\Auth\Jobs;

use App\Domains\Auth\LoginCodes;
use App\Domains\Auth\Mail\LoginCodeMail;
use App\Domains\Auth\Models\LoginChallenge;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Emails a sign-in code ({@see LoginCodeMail}) and records when it was sent.
 */
class SendLoginCodeEmailJob implements ShouldQueue
{
    use Queueable;

    /**
     * The code travels encrypted, because a queued payload shows
     * up in the queue, worker logs, failed jobs and monitoring
     * tools, and the code signs someone in until it expires.
     *
     * @param  positive-int  $loginChallengeId
     */
    public function __construct(
        public readonly int $loginChallengeId,
        public readonly string $encryptedCode,
    ) {
        //
    }

    public function handle(LoginCodes $loginCodes): void
    {
        $challenge = LoginChallenge::query()->find($this->loginChallengeId);

        // A backlog can reach a code after it expired or a newer one replaced it.
        if (! $challenge?->isActive() || filled($challenge->email_sent_at)) {
            return;
        }

        Mail::to($challenge->email)->send(
            new LoginCodeMail(
                encryptedCode: $this->encryptedCode,
                expiresAt: $challenge->expires_at,
                signInUrl: $loginCodes->link($challenge),
            )
        );

        $challenge->update(['email_sent_at' => CarbonImmutable::now()]);
    }
}
