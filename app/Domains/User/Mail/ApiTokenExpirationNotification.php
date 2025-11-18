<?php

declare(strict_types=1);

namespace App\Domains\User\Mail;

use App\Domains\User\Models\ApiToken;
use App\Domains\User\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\Attributes\WithoutRelations;

class ApiTokenExpirationNotification extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        #[WithoutRelations]
        public readonly User $user,
        public readonly ApiToken $token,
        public readonly int $daysUntilExpiration,
    ) {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'API Token Expiring Soon - Action Required',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.api-token-expiration',
            with: [
                'user' => $this->user,
                'token' => $this->token,
                'daysUntilExpiration' => $this->daysUntilExpiration,
                'expirationDate' => $this->token->valid_to?->format('F j, Y \a\t g:i A T'),
            ],
        );
    }
}
