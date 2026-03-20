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
        $baseQuery = Audit::query()->roleActivity();

        $assignmentCount = (clone $baseQuery)->where('event', 'role_assigned')->count();
        $removalCount = (clone $baseQuery)->where('event', 'role_removed')->count();
        $lastActivity = (clone $baseQuery)->max('created_at');

        $recentAssignments = (clone $baseQuery)
            ->where('event', 'role_assigned')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $recentRemovals = (clone $baseQuery)
            ->where('event', 'role_removed')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

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
