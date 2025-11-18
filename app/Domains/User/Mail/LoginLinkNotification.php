<?php

declare(strict_types=1);

namespace App\Domains\User\Mail;

use App\Domains\User\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;

/**
 * Email notification containing a login link for passwordless authentication.
 */
class LoginLinkNotification extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly User $user,
        public readonly string $encryptedToken,
    ) {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Sign in to ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        $rawToken = Crypt::decryptString($this->encryptedToken);
        $url = $this->generateLoginLinkUrl($rawToken);

        return new Content(
            markdown: 'mail.auth.login-link',
            with: [
                'user' => $this->user,
                'url' => $url,
                'expirationMinutes' => config('auth.local.login_link_expiration_minutes'),
            ]
        );
    }

    private function generateLoginLinkUrl(string $rawToken): string
    {
        return URL::route('login-link.verify', ['token' => $rawToken]);
    }
}
