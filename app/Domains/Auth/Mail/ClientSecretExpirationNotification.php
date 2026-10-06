<?php

declare(strict_types=1);

namespace App\Domains\Auth\Mail;

use App\Domains\Auth\Models\OAuthClient;
use App\Domains\Core\Formatting\CountInWords;
use App\Domains\Core\Formatting\NorthwesternDateTime;
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
            subject: 'A service client secret expires in ' . CountInWords::of($this->daysUntilExpiration, 'day'),
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
                'expiresIn' => CountInWords::of($this->daysUntilExpiration, 'day'),
                'expiresAt' => $this->client->secret_expires_at ? NorthwesternDateTime::format($this->client->secret_expires_at, config('app.schedule_timezone')) : null,
                'expiresOn' => $this->client->secret_expires_at ? NorthwesternDateTime::date($this->client->secret_expires_at, config('app.schedule_timezone')) : null,
                'lastUsedAt' => $this->client->last_used_at ? NorthwesternDateTime::format($this->client->last_used_at, config('app.schedule_timezone')) : null,
            ],
        );
    }
}
