<?php

declare(strict_types=1);

namespace App\Domains\User\Mail;

use App\Domains\User\Enums\AuthTypeEnum;
use App\Domains\User\Jobs\SendLoginCodeEmailJob;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Crypt;

/**
 * Notification email containing a login verification code for {@see AuthTypeEnum::LOCAL} users.
 *
 * @see SendLoginCodeEmailJob
 */
class LoginCodeNotification extends Mailable
{
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
