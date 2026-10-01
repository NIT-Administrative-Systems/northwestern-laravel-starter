<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleActivity\Widgets;

use App\Domains\User\Models\Audit;
use Carbon\Carbon;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RoleActivityStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $recentSince = now()->subDays(7);

        /**
         * One pass over the role activity index instead of a query per stat.
         *
         * @var object{assignments: int, removals: int, recent_assignments: int, recent_removals: int, last_activity: string|null} $stats
         */
        $stats = Audit::query()
            ->roleActivity()
            ->toBase()
            ->selectRaw("count(*) filter (where event = 'role_assigned') as assignments")
            ->selectRaw("count(*) filter (where event = 'role_removed') as removals")
            ->selectRaw("count(*) filter (where event = 'role_assigned' and created_at >= ?) as recent_assignments", [$recentSince])
            ->selectRaw("count(*) filter (where event = 'role_removed' and created_at >= ?) as recent_removals", [$recentSince])
            ->selectRaw('max(created_at) as last_activity')
            ->first();

        $assignmentCount = (int) $stats->assignments;
        $removalCount = (int) $stats->removals;
        $recentAssignments = (int) $stats->recent_assignments;
        $recentRemovals = (int) $stats->recent_removals;
        $lastActivity = $stats->last_activity;

        return [
            Stat::make('Assignments', number_format($assignmentCount))
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('success')
                ->description($recentAssignments . ' in last 7 days')
                ->descriptionIcon(Heroicon::ArrowTrendingUp),

            Stat::make('Removals', number_format($removalCount))
                ->icon(Heroicon::OutlinedUserMinus)
                ->color('danger')
                ->description($recentRemovals . ' in last 7 days')
                ->descriptionIcon(Heroicon::ArrowTrendingDown),

            Stat::make('Last Activity', $lastActivity
                ? Carbon::parse($lastActivity)->diffForHumans()
                : 'No activity')
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->description($lastActivity
                    ? Carbon::parse($lastActivity)
                        ->setTimezone(auth()->user()->timezone ?? config('app.timezone'))
                        ->format(config('platform.datetime_display_format', 'M j, Y g:i A'))
                    : null),
        ];
    }
}
