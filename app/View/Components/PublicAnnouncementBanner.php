<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Domains\Support\Models\Announcement;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The most important live announcement for signed-out visitors, under the header
 * on public pages and the sign-in pages. Visitors can't dismiss it, and the page
 * that lists announcements needs sign-in, so it shows in full until it ends.
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
