<?php

declare(strict_types=1);

namespace App\Domains\Auth\Notifications;

use App\Domains\Auth\Models\OAuthConnection;
use App\Domains\User\Models\User;
use App\Filament\App\Clusters\AccountCluster\Pages\ConnectedApplications;
use App\Providers\Filament\AppPanelProvider;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person an application has connected to their account, so one they don't recognize
 * can be disconnected. Always shown in the app's notification bell; emailed too unless they
 * turned that off in their preferences.
 */
class ApplicationConnectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly OAuthConnection $oauthConnection,
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->preferences->emailWhenApplicationConnects && filled($notifiable->email)
            ? ['database', 'mail']
            : ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title("{$this->applicationName()} connected to your account")
            ->body('If you didn\'t do this, disconnect it.')
            ->icon('heroicon-o-link')
            ->actions([
                Action::make('review')
                    ->label('Review connected applications')
                    ->url(ConnectedApplications::getUrl(panel: AppPanelProvider::ID)),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("{$this->applicationName()} connected to your account")
            ->greeting('Hello ' . ($notifiable->first_name ?: $notifiable->full_name) . ',')
            ->line("**{$this->applicationName()}** can now use " . config('app.name') . ' on your behalf.')
            ->line('If you didn\'t connect it, disconnect it and contact support.')
            ->action('Review connected applications', ConnectedApplications::getUrl(panel: AppPanelProvider::ID));
    }

    private function applicationName(): string
    {
        return $this->oauthConnection->oauth_client->name ?? 'An application';
    }
}
