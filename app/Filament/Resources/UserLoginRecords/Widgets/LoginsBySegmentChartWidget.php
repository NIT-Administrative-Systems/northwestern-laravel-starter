<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserLoginRecords\Widgets;

use App\Domains\User\Enums\UserSegment;
use App\Domains\User\Models\UserLoginRecord;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class LoginsBySegmentChartWidget extends ChartWidget
{
    protected ?string $heading = 'Logins by Segment';

    public ?string $startDate = null;

    public ?string $endDate = null;

    protected ?string $maxHeight = '235px';

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

    protected function getData(): array
    {
        /**
         * @var Collection<int, object{
         *     segment: UserSegment,
         *     count: int,
         * }> $segmentCounts
         */
        $segmentCounts = UserLoginRecord::query()
            ->whereBetween('logged_in_at', [$this->startDate, $this->endDate])
            ->selectRaw('segment, COUNT(*) as count')
            ->groupBy('segment')
            ->orderByDesc('count')
            ->withCasts(['count' => 'int'])
            ->get();

        $labels = [];
        $chartValues = [];
        $colors = [];

        foreach ($segmentCounts as $segmentCount) {
            $segmentEnum = $segmentCount->segment;
            $count = $segmentCount->count;

            $labels[] = $segmentEnum->getLabel();
            $chartValues[] = $count;

            $colors[] = match ($segmentEnum->getColor()) {
                'danger' => 'rgb(239, 68, 68)',
                'success' => 'rgb(16, 185, 129)',
                'warning' => 'rgb(245, 158, 11)',
                'info' => 'rgb(59, 130, 246)',
                'gray' => 'rgb(107, 114, 128)',
                default => 'rgb(59, 130, 246)',
            };
        }

        $total = array_sum($chartValues);

        $labelsWithPercentages = array_map(function (string $label, int $count) use ($total) {
            $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;

            return "{$label} ({$percentage}%)";
        }, $labels, $chartValues);

        return [
            'datasets' => [
                [
                    'label' => 'Logins',
                    'data' => $chartValues,
                    'backgroundColor' => $colors,
                    'borderColor' => 'rgba(255, 255, 255, 0.7)',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labelsWithPercentages,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'right',
                ],
            ],
            'cutout' => '55%',
        ];
    }
}
