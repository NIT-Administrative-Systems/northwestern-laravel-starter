<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domains\Support\Models\Announcement;
use App\Domains\User\Models\User;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;

/**
 * Every announcement the person's audience can see: live ones first, then past ones, dismissed
 * or not. Reached from the announcement banner and the Help menu.
 */
class Announcements extends Page
{
    use WithPagination;

    public const int PER_PAGE = 10;

    protected static ?string $title = 'Announcements';

    protected static ?string $slug = 'announcements';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.app.pages.announcements';

    /**
     * @return LengthAwarePaginator<int, Announcement>
     */
    public function getAnnouncements(): LengthAwarePaginator
    {
        /** @var User $user */
        $user = auth()->user();

        return Announcement::query()
            ->started()
            ->visibleTo($user)
            // Live first (no end, or an end still to come), then by start, newest first.
            ->orderByRaw('CASE WHEN ends_at IS NULL OR ends_at > ? THEN 0 ELSE 1 END', [now()])
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);
    }
}
