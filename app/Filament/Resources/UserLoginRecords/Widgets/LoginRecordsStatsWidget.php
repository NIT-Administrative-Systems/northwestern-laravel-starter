<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserLoginRecords\Widgets;

use App\Domains\User\Models\UserLoginRecord;
use Carbon\Carbon;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class LoginRecordsStatsWidget extends BaseWidget
{
    public ?string $startDate = null;

    public ?string $endDate = null;

    protected ?string $pollingInterval = null;

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

    protected function getStats(): array
    {
        return [
            $this->getTotalLoginsState(),
            $this->getUniqueUsersState(),
            $this->getMostActiveSegmentStat(),
            $this->getAverageLoginsPerDayStat(),
        ];
    }

    protected function getTotalLoginsState(): Stat
    {
        $total = UserLoginRecord::query()
            ->whereBetween('logged_in_at', [$this->startDate, $this->endDate])
            ->count();

        return Stat::make('Total Logins', number_format($total))
            ->icon(Heroicon::ArrowRightEndOnRectangle)
            ->color('primary');
    }

    protected function getUniqueUsersState(): Stat
    {
        $uniqueUsers = UserLoginRecord::query()
            ->whereBetween('logged_in_at', [$this->startDate, $this->endDate])
            ->distinct('user_id')
            ->count('user_id');

        return Stat::make('Unique Users', number_format($uniqueUsers))
            ->icon(Heroicon::UserGroup)
            ->color('success');
    }

    protected function getMostActiveSegmentStat(): Stat
    {
        $segmentCounts = UserLoginRecord::query()
            ->whereBetween('logged_in_at', [$this->startDate, $this->endDate])
            ->selectRaw('segment, count(*) as count')
            ->groupBy('segment')
            ->orderByDesc('count')
            ->get();

        if ($segmentCounts->isEmpty()) {
            return Stat::make('Most Active Segment', 'No Data')
                ->icon(Heroicon::UserGroup)
                ->color('gray');
        }

        /** @var UserLoginRecord $topSegment */
        $topSegment = $segmentCounts->first();
        $segmentEnum = $topSegment->segment;

        return Stat::make('Most Active Segment', $segmentEnum->getLabel())
            ->icon(Heroicon::UserGroup)
            ->color($segmentEnum->getColor());
    }

    protected function getAverageLoginsPerDayStat(): Stat
    {
        $start = Carbon::parse($this->startDate);
        $end = Carbon::parse($this->endDate);
        $daysDiff = $start->diffInDays($end) + 1;

        $total = UserLoginRecord::query()
            ->whereBetween('logged_in_at', [$this->startDate, $this->endDate])
            ->count();

        $averagePerDay = $daysDiff > 0
            ? (int) round($total / $daysDiff)
            : 0;

        return Stat::make('Average Logins/Day', number_format($averagePerDay))
            ->icon(Heroicon::CalendarDays)
            ->color('warning');
    }
}
