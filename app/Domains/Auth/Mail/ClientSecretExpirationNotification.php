<?php

declare(strict_types=1);

namespace App\Domains\Auth\Mail;

use App\Domains\Auth\Models\OAuthClient;
use App\Domains\User\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\Attributes\WithoutRelations;

class ClientSecretExpirationNotification extends Mailable implements ShouldQueue
{
    use Queueable;

    /** @param  positive-int  $daysUntilExpiration */
    public function __construct(
        #[WithoutRelations]
        public readonly User $user,
        public readonly OAuthClient $client,
        public readonly int $daysUntilExpiration,
    ) {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'API Client Secret Expiring Soon - Action Required',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.client-secret-expiration',
            with: [
                'user' => $this->user,
                'client' => $this->client,
                'daysUntilExpiration' => $this->daysUntilExpiration,
                'expirationDate' => $this->client->secret_expires_at?->format('F j, Y \a\t g:i A T'),
            ],
        );
    }
}
