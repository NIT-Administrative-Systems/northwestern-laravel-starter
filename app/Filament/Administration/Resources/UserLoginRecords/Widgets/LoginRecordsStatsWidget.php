<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\UserLoginRecords\Widgets;

use App\Domains\User\Models\UserLoginRecord;
use App\Filament\Administration\Resources\UserLoginRecords\Widgets\Concerns\TracksBroadcastDateRange;
use Carbon\Carbon;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LoginRecordsStatsWidget extends BaseWidget
{
    use TracksBroadcastDateRange;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        /** @var object{total: int, unique_users: int} $totals */
        $totals = UserLoginRecord::query()
            ->whereBetween('logged_in_at', [$this->startDate, $this->endDate])
            ->toBase()
            ->selectRaw('count(*) as total, count(distinct user_id) as unique_users')
            ->first();

        return [
            $this->getTotalLoginsState((int) $totals->total),
            $this->getUniqueUsersState((int) $totals->unique_users),
            $this->getMostActiveSegmentStat(),
            $this->getAverageLoginsPerDayStat((int) $totals->total),
        ];
    }

    protected function getTotalLoginsState(int $total): Stat
    {
        return Stat::make('Total Sign-Ins', number_format($total))
            ->icon(Heroicon::ArrowRightEndOnRectangle)
            ->color('primary');
    }

    protected function getUniqueUsersState(int $uniqueUsers): Stat
    {
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

    protected function getAverageLoginsPerDayStat(int $total): Stat
    {
        $start = Carbon::parse($this->startDate);
        $end = Carbon::parse($this->endDate);
        $daysDiff = $start->diffInDays($end) + 1;

        $averagePerDay = $daysDiff > 0
            ? (int) round($total / $daysDiff)
            : 0;

        return Stat::make('Average Sign-Ins per Day', number_format($averagePerDay))
            ->icon(Heroicon::CalendarDays)
            ->color('warning');
    }
}
