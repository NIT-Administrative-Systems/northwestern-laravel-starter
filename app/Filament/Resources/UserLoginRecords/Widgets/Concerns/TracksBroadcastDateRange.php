<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserLoginRecords\Widgets\Concerns;

use App\Filament\Resources\UserLoginRecords\Widgets\DateRangeFilterWidget;
use Carbon\Carbon;
use Livewire\Attributes\On;

/**
 * Shared state and listeners for widgets that react to the user login records
 * {@see DateRangeFilterWidget} broadcasts.
 *
 * Defaults to the last 30 days in the authenticated user's timezone and
 * re-renders the widget whenever the filter widget emits an updated range.
 */
trait TracksBroadcastDateRange
{
    public ?string $startDate = null;

    public ?string $endDate = null;

    public function mount(): void
    {
        $now = Carbon::now(auth()->user()->timezone);

        $this->startDate = $now->copy()->subDays(29)->startOfDay()->utc()->toDateTimeString();
        $this->endDate = $now->copy()->endOfDay()->utc()->toDateTimeString();
    }

    #[On(DateRangeFilterWidget::EVENT_DATE_RANGE_UPDATED)]
    public function updateDateRange(string $startDate, string $endDate): void
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;

        $this->dispatch('$refresh');
    }
}
