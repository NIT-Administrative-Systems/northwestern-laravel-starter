<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserLoginRecords\Widgets;

use App\Domains\User\Models\UserLoginRecord;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class LoginTrendsChartWidget extends ChartWidget
{
    public ?string $startDate = null;

    public ?string $endDate = null;

    protected ?string $maxHeight = '300px';

    protected ?string $pollingInterval = null;

    public function getHeading(): ?string
    {
        if ($this->startDate && $this->endDate) {
            $start = Carbon::parse($this->startDate, 'UTC')->setTimezone(auth()->user()->timezone);
            $end = Carbon::parse($this->endDate, 'UTC')->setTimezone(auth()->user()->timezone);

            if ($start->isSameDay($end)) {
                return '24-Hour Login Trends';
            }
        }

        return 'Login Trends';
    }

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

    protected function getData(): array
    {
        $timezone = auth()->user()->timezone;
        $startInUserTz = Carbon::parse($this->startDate, 'UTC')->setTimezone($timezone);
        $endInUserTz = Carbon::parse($this->endDate, 'UTC')->setTimezone($timezone);

        // For a single day ("Today" and "Yesterday"), show hourly data
        if ($startInUserTz->isSameDay($endInUserTz)) {
            /** @var Collection<int, object{hour: string, total_count: string, unique_count: string}> $hourlyStats */
            $hourlyStats = UserLoginRecord::query()
                ->whereBetween('logged_in_at', [$this->startDate, $this->endDate])
                ->selectRaw("
                    EXTRACT(HOUR FROM logged_in_at AT TIME ZONE 'UTC' AT TIME ZONE ?) as hour,
                    COUNT(*) as total_count,
                    COUNT(DISTINCT user_id) as unique_count
                ", [$timezone])
                ->groupBy('hour')
                ->orderBy('hour')
                ->get()
                ->keyBy('hour');

            $dates = [];
            $totalData = [];
            $uniqueData = [];

            for ($hour = 0; $hour < 24; $hour++) {
                // Format hour directly - the hour from DB is already in user's timezone
                $dates[] = date('g A', mktime($hour, 0));
                $stats = $hourlyStats->get((string) $hour) ?? (object) [
                    'total_count' => 0,
                    'unique_count' => 0,
                ];

                $totalData[] = (int) $stats->total_count;
                $uniqueData[] = (int) $stats->unique_count;
            }
        } else {
            // Daily data for multi-day range - group by date in user's timezone
            /** @var Collection<int, object{date: string, total_count: string, unique_count: string}> $dailyStats */
            $dailyStats = UserLoginRecord::query()
                ->whereBetween('logged_in_at', [$this->startDate, $this->endDate])
                ->selectRaw("
                    DATE(logged_in_at AT TIME ZONE 'UTC' AT TIME ZONE ?) as date,
                    COUNT(*) as total_count,
                    COUNT(DISTINCT user_id) as unique_count
                ", [$timezone])
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->keyBy('date');

            $dates = [];
            $totalData = [];
            $uniqueData = [];

            // Iterate through dates in user's timezone to match query results
            $current = $startInUserTz->copy()->startOfDay();

            while ($current->lte($endInUserTz)) {
                $dateKey = $current->format('Y-m-d');
                $dates[] = $current->format('M d');
                $stats = $dailyStats->get($dateKey) ?? (object) [
                    'total_count' => 0,
                    'unique_count' => 0,
                ];

                $totalData[] = (int) $stats->total_count;
                $uniqueData[] = (int) $stats->unique_count;
                $current->addDay();
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Logins',
                    'data' => $totalData,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Unique Users',
                    'data' => $uniqueData,
                    'borderColor' => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $dates,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
