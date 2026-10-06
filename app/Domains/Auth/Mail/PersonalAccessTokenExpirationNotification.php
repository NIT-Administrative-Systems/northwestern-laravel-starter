<?php

declare(strict_types=1);

namespace App\Domains\Auth\Mail;

use App\Domains\Auth\Models\OAuthToken;
use App\Domains\User\Models\User;
use App\Filament\App\Clusters\AccountCluster\Pages\AccessTokens;
use App\Filament\App\Clusters\AccountCluster\Pages\Preferences;
use App\Providers\Filament\AppPanelProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\Attributes\WithoutRelations;
use Northwestern\SysDev\Chassis\Formatting\CountInWords;
use Northwestern\SysDev\Chassis\Formatting\NorthwesternDateTime;

class PersonalAccessTokenExpirationNotification extends Mailable implements ShouldQueue
{
    use Queueable;

    /** @param  positive-int  $daysUntilExpiration */
    public function __construct(
        #[WithoutRelations]
        public readonly User $user,
        public readonly OAuthToken $token,
        public readonly int $daysUntilExpiration,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your personal access token expires in ' . CountInWords::of($this->daysUntilExpiration, 'day'));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.personal-access-token-expiration',
            with: [
                'user' => $this->user,
                'token' => $this->token,
                'expiresIn' => CountInWords::of($this->daysUntilExpiration, 'day'),
                'expiresAt' => $this->token->expires_at ? NorthwesternDateTime::format($this->token->expires_at, $this->user->timezone) : null,
                'accessTokensUrl' => AccessTokens::getUrl(panel: AppPanelProvider::ID),
                'preferencesUrl' => Preferences::getUrl(panel: AppPanelProvider::ID),
            ],
        );
    }
}
