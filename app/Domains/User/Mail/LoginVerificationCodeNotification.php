<?php

declare(strict_types=1);

namespace App\Domains\User\Mail;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LoginVerificationCodeNotification extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly CarbonImmutable $expiresAt,
    ) {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('%s - %s Verification Code', $this->code, config('app.name')),
        );
    }

    public function content(): Content
    {
        $secondsRemaining = CarbonImmutable::now()->diffInSeconds($this->expiresAt);
        $expiresInMinutes = max(1, (int) ceil($secondsRemaining / 60));

        return new Content(
            markdown: 'mail.auth.login-code',
            with: [
                'code' => $this->code,
                'expiresInMinutes' => $expiresInMinutes,
            ]
        );
    }
}
