<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiRequestLogs\Widgets;

use Filament\Support\RawJs;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class TopApiRequestsByEndpointChartWidget extends BaseApiRequestChartWidget
{
    public function getDescription(): HtmlString|string|null
    {
        if (! $this->startDate || ! $this->endDate) {
            return null;
        }

        $baseQuery = $this->baseQuery();

        /** @var Collection<int, object{
         *     path: string,
         *     request_count: int
         * }> $endpointStats
         */
        $endpointStats = (clone $baseQuery)
            ->selectRaw('path, COUNT(*) as request_count')
            ->groupBy('path')
            ->orderByDesc('request_count')
            ->limit(10)
            ->get();

        if ($endpointStats->isEmpty()) {
            return new HtmlString(
                view('filament.resources.api-request-logs.widgets.chart-description', [
                    'leftLabel' => 'Top Endpoint',
                    'leftValue' => 'No data available',
                    'leftMeta' => '0 requests',
                    'rightGridClass' => 'grid-cols-2',
                    'rightColumns' => [],
                ])->render()
            );
        }

        $topEndpoint = $endpointStats->first();
        $topEndpointPath = $topEndpoint->path;
        $topEndpointCount = (int) $topEndpoint->request_count;

        return new HtmlString(
            view('filament.resources.api-request-logs.widgets.chart-description', [
                'leftLabel' => 'Top Endpoint',
                'leftValue' => $topEndpointPath,
                'leftMeta' => sprintf(
                    '%s %s',
                    $this->formatNumber($topEndpointCount),
                    str('request')->plural($topEndpointCount),
                ),
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
         *     request_count: int
         * }> $endpointStats
         */
        $endpointStats = $this->baseQuery()
            ->selectRaw('path, COUNT(*) as request_count')
            ->groupBy('path')
            ->orderByDesc('request_count')
            ->limit(10)
            ->get();

        if ($endpointStats->isEmpty()) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $labels = $endpointStats->pluck('path')->all();
        $data = $endpointStats->pluck('request_count')->map(fn ($v) => (int) $v)->all();

        return [
            'datasets' => [
                [
                    'label' => 'Requests',
                    'data' => $data,
                    'backgroundColor' => 'rgb(34, 197, 94)',
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
                        if (value >= 1_000_000) {
                            formatted = (value / 1_000_000).toFixed(1) + 'M';
                        } else if (value >= 1_000) {
                            formatted = (value / 1_000).toFixed(1) + 'k';
                        } else {
                            formatted = value.toString();
                        }

                        return ` ${formatted} requests`;
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
                },
            },
            y: {
                grid: {
                    display: false,
                },
                ticks: {
                    autoSkip: false, // show all endpoint labels
                    callback: function (value, index, ticks) {
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
