<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Domains\Support\Models\Announcement;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The most important live announcement, for visitors on public and sign-in pages.
 *
 * Visitors can't dismiss it or open the Announcements
 * page, so it shows in full until its time is over.
 */
class PublicAnnouncementBanner extends Component
{
    public ?Announcement $announcement;

    public function __construct()
    {
        // A failing lookup shows nothing rather than failing the page.
        $this->announcement = rescue(
            fn (): ?Announcement => Announcement::query()->live()->visibleTo(null)->byImportance()->first(),
            null,
            report: false,
        );
    }

    public function shouldRender(): bool
    {
        return $this->announcement instanceof Announcement;
    }

    public function render(): View
    {
        return view('components.public-announcement-banner');
    }
}
