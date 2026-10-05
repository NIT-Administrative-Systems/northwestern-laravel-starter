<?php

declare(strict_types=1);

namespace App\Filament\App\Livewire;

use App\Domains\Support\Actions\Announcements\DismissAnnouncement;
use App\Domains\Support\Models\Announcement;
use App\Domains\User\Models\User;
use App\Filament\App\Pages\Announcements;
use App\Providers\Filament\AppPanelProvider;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The announcement banner at the top of every app-panel page: the single most important live
 * announcement the person hasn't dismissed, with a count of the others. Dismissing one shows the
 * next in its place.
 */
class AnnouncementBanner extends Component
{
    public function dismiss(int $announcementId, DismissAnnouncement $dismiss): void
    {
        $dismiss(Announcement::query()->findOrFail($announcementId), $this->user());
    }

    public function render(): View
    {
        $user = $this->user();
        $announcements = Announcement::query()->live()->visibleTo($user)->notDismissedBy($user)->byImportance();

        return view('filament.app.livewire.announcement-banner', [
            'announcement' => (clone $announcements)->first(),
            'others' => max(0, $announcements->count() - 1),
            'dismissible' => ! $user->isImpersonated(),
            'pageUrl' => Announcements::getUrl(panel: AppPanelProvider::ID),
        ]);
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
