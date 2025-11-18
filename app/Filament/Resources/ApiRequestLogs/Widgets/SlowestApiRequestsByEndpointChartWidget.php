<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiRequestLogs\Widgets;

use Filament\Support\RawJs;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class SlowestApiRequestsByEndpointChartWidget extends BaseApiRequestChartWidget
{
    public function getDescription(): HtmlString|string|null
    {
        if (! $this->startDate || ! $this->endDate) {
            return null;
        }

        $baseQuery = $this->baseQuery();

        /** @var Collection<int, object{
         *     path: string,
         *     p95_duration: float|null,
         *     request_count: int
         * }> $endpointStats
         */
        $endpointStats = (clone $baseQuery)
            ->selectRaw('
                path,
                PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY duration_ms) as p95_duration,
                COUNT(*) as request_count
            ')
            ->groupBy('path')
            ->orderByDesc('p95_duration')
            ->limit(10)
            ->get();

        if ($endpointStats->isEmpty()) {
            return new HtmlString(
                view('filament.resources.api-request-logs.widgets.chart-description', [
                    'leftLabel' => 'Slowest Endpoint (P95)',
                    'leftValue' => 'No data available',
                    'leftMeta' => '',
                    'rightGridClass' => 'grid-cols-2',
                    'rightColumns' => [],
                ])->render()
            );
        }

        /** @var object{
         *     path: string,
         *     p95_duration: float|null,
         *     request_count: int
         * } $slowest
         */
        $slowest = $endpointStats->first();

        $slowestPath = $slowest->path;
        $slowestP95 = (float) ($slowest->p95_duration ?? 0.0);

        return new HtmlString(
            view('filament.resources.api-request-logs.widgets.chart-description', [
                'leftLabel' => 'Slowest Endpoint (P95)',
                'leftValue' => $slowestPath,
                'leftMeta' => $this->formatDuration($slowestP95),
                'rightGridClass' => 'grid-cols-2',
                'rightColumns' => [],
            ])->render()
        );
    }

    protected function getData(): array
    {
        if (! $this->startDate || ! $this->endDate) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        /** @var Collection<int, object{
         *     path: string,
         *     p95_duration: float|null,
         *     request_count: int
         * }> $endpointStats
         */
        $endpointStats = $this->baseQuery()
            ->selectRaw('
                path,
                PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY duration_ms) as p95_duration,
                COUNT(*) as request_count
            ')
            ->groupBy('path')
            ->orderByDesc('p95_duration')
            ->limit(10)
            ->get();

        if ($endpointStats->isEmpty()) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $labels = $endpointStats->pluck('path')->all();
        $data = $endpointStats
            ->pluck('p95_duration')
            ->map(fn ($value) => (float) ($value ?? 0.0))
            ->all();

        return [
            'datasets' => [
                [
                    'label' => 'P95 Duration',
                    'data' => $data,
                    'backgroundColor' => 'rgb(234, 179, 8)', // yellow
                    'borderWidth' => 0,
                    'borderRadius' => 4,
                    'maxBarThickness' => 18,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',

            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    mode: 'nearest',
                    intersect: true,
                    callbacks: {
                        label: function (context) {
                            const value = (context.parsed.x ?? context.parsed.y ?? 0);

                            let formatted;
                            if (value >= 1000) {
                                formatted = (value / 1000).toFixed(1) + ' s';
                            } else {
                                formatted = Math.round(value) + ' ms';
                            }

                            return `P95: ${formatted}`;
                        },
                    },
                },
            },

            scales: {
                x: {
                    beginAtZero: true,
                    grid: {
                        display: false,
                    },
                    ticks: {
                        precision: 0,
                        callback: function (value) {
                            if (value >= 1000) {
                                return (value / 1000).toFixed(1) + ' s';
                            }

                            return value + ' ms';
                        },
                    },
                },
                y: {
                    grid: {
                        display: false,
                    },
                    ticks: {
                        autoSkip: false,
                        callback: function (value) {
                            const label = this.getLabelForValue(value) ?? '';

                            const maxLength = 40;
                            return label.length > maxLength
                                ? label.slice(0, maxLength - 1) + '…'
                                : label;
                        },
                    },
                },
            },
        }
        JS);
    }
}
