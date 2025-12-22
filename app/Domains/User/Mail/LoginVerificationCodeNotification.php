<?php

declare(strict_types=1);

namespace App\Domains\User\Mail;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Crypt;

class LoginVerificationCodeNotification extends Mailable implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $encryptedCode  The verification code encrypted at the time the challenge is issued.
     *
     * Queued mailables are serialized and transported through the queue (e.g., SES), and the payload
     * commonly becomes visible in multiple places:
     * - The queue transport itself
     * - Worker logs and retry/failure payloads
     * - APM tools that capture job context
     *
     * While the verification code is short-lived, it is still a valid authentication factor during its
     * lifetime. Encrypting it ensures the raw code is not exposed in any of these places.
     */
    public function __construct(
        public readonly string $encryptedCode,
        public readonly CarbonImmutable $expiresAt,
    ) {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Sign in to %s', config('app.name')),
        );
    }

    public function content(): Content
    {
        $secondsRemaining = CarbonImmutable::now()->diffInSeconds($this->expiresAt);
        $expiresInMinutes = max(1, (int) ceil($secondsRemaining / 60));
        $code = Crypt::decryptString($this->encryptedCode);

        return new Content(
            markdown: 'mail.auth.login-code',
            with: [
                'code' => $code,
                'expiresInMinutes' => $expiresInMinutes,
            ]
        );
    }
}
