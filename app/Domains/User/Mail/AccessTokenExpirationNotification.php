<?php

declare(strict_types=1);

namespace App\Domains\User\Mail;

use App\Domains\User\Models\AccessToken;
use App\Domains\User\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\Attributes\WithoutRelations;

class AccessTokenExpirationNotification extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        #[WithoutRelations]
        public readonly User $user,
        public readonly AccessToken $token,
        public readonly int $daysUntilExpiration,
    ) {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Access Token Expiring Soon - Action Required',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.access-token-expiration',
            with: [
                'user' => $this->user,
                'token' => $this->token,
                'daysUntilExpiration' => $this->daysUntilExpiration,
                'expirationDate' => $this->token->expires_at?->format('F j, Y \a\t g:i A T'),
            ],
        );
    }
}
