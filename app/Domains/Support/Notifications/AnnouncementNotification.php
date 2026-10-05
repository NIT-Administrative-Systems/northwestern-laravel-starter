<?php

declare(strict_types=1);

namespace App\Domains\Support\Notifications;

use App\Domains\Support\Models\Announcement;
use App\Domains\User\Models\User;
use App\Filament\App\Pages\Announcements;
use App\Providers\Filament\AppPanelProvider;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells a person about an announcement whose author chose to notify its audience. Always shown
 * in the app's notification bell; emailed too unless they turned that off in their preferences.
 */
class AnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Announcement $announcement,
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->preferences->emailAnnouncements && filled($notifiable->email)
            ? ['database', 'mail']
            : ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->announcement->title)
            ->body(Str::limit(strip_tags((string) $this->announcement->bodyHtml()), 160))
            ->icon($this->announcement->severity->getIcon())
            ->iconColor($this->announcement->severity->getColor())
            ->actions([
                Action::make('read')
                    ->label('Read announcement')
                    ->url($this->url()),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject($this->announcement->title)
            ->greeting('Hello ' . ($notifiable->first_name ?: $notifiable->full_name) . ',')
            ->line(config('app.name') . ' has an announcement:')
            ->line("**{$this->announcement->title}**")
            ->line($this->announcement->body)
            ->action('Read announcement', $this->url())
            ->line('You can turn off these emails in your Account preferences.');
    }

    private function url(): string
    {
        return Announcements::getUrl(panel: AppPanelProvider::ID) . "#announcement-{$this->announcement->getKey()}";
    }
}
