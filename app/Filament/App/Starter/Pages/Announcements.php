<?php

declare(strict_types=1);

namespace App\Filament\App\Starter\Pages;

use App\Domains\Support\Enums\AnnouncementStatus;
use App\Domains\Support\Models\Announcement;
use App\Domains\User\Models\User;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;
use Northwestern\SysDev\Chassis\Formatting\NorthwesternDateTime;

/**
 * Every announcement the person's audience can see, live ones first, then
 * past ones, dismissed or not. The banner and the Help menu link to it.
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
        return Announcement::query()
            ->started()
            ->visibleTo($this->user())
            // Live first (no end, or an end still to come), then by start, newest first.
            ->orderByRaw('CASE WHEN ends_at IS NULL OR ends_at > ? THEN 0 ELSE 1 END', [now()])
            ->latest('starts_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);
    }

    /**
     * Get when an announcement started and, when it has
     * one, its end, in the person's chosen timezone.
     *
     * "June 1 · Until 5 p.m. Monday, August 31" or "June 1 · Ended August 31"
     */
    public function dateline(Announcement $announcement): string
    {
        $timezone = $this->user()->timezone ?: config('app.timezone');
        $started = $announcement->starts_at !== null
            ? NorthwesternDateTime::date($announcement->starts_at, $timezone, withWeekday: false)
            : '';

        if ($announcement->ends_at === null) {
            return $started;
        }

        $ending = $announcement->status === AnnouncementStatus::Live
            ? 'Until ' . NorthwesternDateTime::format($announcement->ends_at, $timezone, withZone: false)
            : 'Ended ' . NorthwesternDateTime::date($announcement->ends_at, $timezone, withWeekday: false);

        return "{$started} · {$ending}";
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
