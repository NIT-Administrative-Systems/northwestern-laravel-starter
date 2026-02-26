<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiRequestLogs\Widgets;

use App\Domains\Auth\Models\ApiRequestLog;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class SlowestApiRequestsByEndpointChartWidget extends BaseApiRequestChartWidget
{
    /** @var Collection<int, ApiRequestLog>|null */
    private ?Collection $cachedEndpointStats = null;

    protected function getData(): array
    {
        if (! $this->startDate || ! $this->endDate) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $threshold = (int) config('api.request_logging.slow_request_threshold_ms');

        $this->cachedEndpointStats = $this->baseQuery()
            ->selectRaw('
                path,
                PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY duration_ms) as p50_duration,
                PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY duration_ms) as p95_duration,
                COUNT(*) as request_count
            ')
            ->groupBy('path')
            ->orderByDesc('p95_duration')
            ->limit(10)
            ->get();

        if ($this->cachedEndpointStats->isEmpty()) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $labels = $this->cachedEndpointStats->pluck('path')->all();

        $p50Data = $this->cachedEndpointStats
            ->pluck('p50_duration')
            ->map(fn ($value) => round((float) ($value ?? 0.0), 2))
            ->all();

        $p95Data = $this->cachedEndpointStats
            ->pluck('p95_duration')
            ->map(fn ($value) => round((float) ($value ?? 0.0), 2))
            ->all();

        $requestCounts = $this->cachedEndpointStats
            ->pluck('request_count')
            ->map(fn ($v) => (int) $v)
            ->all();

        // Color-code P95 bars: green < threshold, amber 1-2x, red > 2x
        $p95Colors = array_map(
            fn (float $v) => match (true) {
                $v > $threshold * 2 => 'rgb(239, 68, 68)',   // red
                $v > $threshold => 'rgb(234, 179, 8)',       // amber
                default => 'rgb(34, 197, 94)',               // green
            },
            $p95Data,
        );

        return [
            'datasets' => [
                [
                    'label' => 'P50 Duration',
                    'data' => $p50Data,
                    'backgroundColor' => 'rgba(148, 163, 184, 0.5)',
                    'borderWidth' => 0,
                    'borderRadius' => 4,
                    'maxBarThickness' => 18,
                    'requestCounts' => $requestCounts,
                ],
                [
                    'label' => 'P95 Duration',
                    'data' => $p95Data,
                    'backgroundColor' => $p95Colors,
                    'borderWidth' => 0,
                    'borderRadius' => 4,
                    'maxBarThickness' => 18,
                    'requestCounts' => $requestCounts,
                ],
            ],
            'labels' => $labels,
        ];
    }

    public function getDescription(): HtmlString|string|null
    {
        if (! $this->startDate || ! $this->endDate || ! $this->cachedEndpointStats instanceof Collection) {
            return null;
        }

        $threshold = (int) config('api.request_logging.slow_request_threshold_ms');

        if ($this->cachedEndpointStats->isEmpty()) {
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

        $slowest = $this->cachedEndpointStats->first();
        $slowestP95 = (float) ($slowest->p95_duration ?? 0.0);
        $slowestP50 = (float) ($slowest->p50_duration ?? 0.0);

        $p95Color = match (true) {
            $slowestP95 > $threshold * 2 => 'danger',
            $slowestP95 > $threshold => 'warning',
            default => 'success',
        };

        return new HtmlString(
            view('filament.resources.api-request-logs.widgets.chart-description', [
                'leftLabel' => 'Slowest Endpoint (P95)',
                'leftValue' => $slowest->path,
                'leftMeta' => null,
                'rightGridClass' => 'grid-cols-2',
                'rightColumns' => [
                    [
                        'label' => 'P50',
                        'value' => $this->formatDuration($slowestP50),
                        'color' => 'success',
                    ],
                    [
                        'label' => 'P95',
                        'value' => $this->formatDuration($slowestP95),
                        'color' => $p95Color,
                    ],
                ],
            ])->render()
        );
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
                            const label = context.dataset.label || '';
                            const value = (context.parsed.x ?? context.parsed.y ?? 0);

                            let formatted;
                            if (value >= 1000) {
                                formatted = (value / 1000).toFixed(1) + ' s';
                            } else {
                                formatted = Math.round(value) + ' ms';
                            }

                            return `${label}: ${formatted}`;
                        },

                        afterBody: function (tooltipItems) {
                            if (!tooltipItems.length) return '';

                            const item = tooltipItems[0];
                            const counts = item.dataset.requestCounts;

                            if (!counts) return '';

                            const count = counts[item.dataIndex];
                            let formatted;
                            if (count >= 1_000_000) {
                                formatted = (count / 1_000_000).toFixed(1) + 'M';
                            } else if (count >= 1_000) {
                                formatted = (count / 1_000).toFixed(1) + 'k';
                            } else {
                                formatted = count.toString();
                            }

                            return `${formatted} total requests`;
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
