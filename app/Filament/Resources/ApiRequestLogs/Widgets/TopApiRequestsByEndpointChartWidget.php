<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiRequestLogs\Widgets;

use App\Domains\Auth\Models\ApiRequestLog;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class TopApiRequestsByEndpointChartWidget extends BaseApiRequestChartWidget
{
    /** @var Collection<int, ApiRequestLog>|null */
    private ?Collection $cachedEndpointStats = null;

    private ?int $cachedTotalCount = null;

    private ?int $cachedUniqueEndpoints = null;

    protected function getData(): array
    {
        if (! $this->startDate || ! $this->endDate) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $baseQuery = $this->baseQuery();

        $this->cachedTotalCount = (int) (clone $baseQuery)->count();

        $this->cachedEndpointStats = (clone $baseQuery)
            ->selectRaw('path, COUNT(*) as request_count')
            ->groupBy('path')
            ->orderByDesc('request_count')
            ->limit(10)
            ->get();

        $this->cachedUniqueEndpoints = (int) (clone $baseQuery)
            ->selectRaw('COUNT(DISTINCT path) as cnt')
            ->value('cnt');

        if ($this->cachedEndpointStats->isEmpty()) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $labels = $this->cachedEndpointStats->pluck('path')->all();
        $data = $this->cachedEndpointStats->pluck('request_count')->map(fn ($v) => (int) $v)->all();

        // Add "Other" category for remaining endpoints
        $topSum = array_sum($data);
        $otherCount = $this->cachedTotalCount - $topSum;

        if ($otherCount > 0) {
            $labels[] = 'Other';
            $data[] = $otherCount;
        }

        // Pre-compute percentages for tooltip access
        $percentages = array_map(
            fn (int $v) => $this->cachedTotalCount > 0 ? round(($v / $this->cachedTotalCount) * 100, 1) : 0,
            $data,
        );

        return [
            'datasets' => [
                [
                    'label' => 'Requests',
                    'data' => $data,
                    'backgroundColor' => array_map(
                        fn (string $label) => $label === 'Other' ? 'rgb(148, 163, 184)' : 'rgb(34, 197, 94)',
                        $labels,
                    ),
                    'borderWidth' => 0,
                    'borderRadius' => 4,
                    'maxBarThickness' => 18,
                    'percentages' => $percentages,
                ],
            ],
            'labels' => $labels,
        ];
    }

    public function getDescription(): HtmlString|string|null
    {
        if (! $this->startDate || ! $this->endDate) {
            return null;
        }

        if (! $this->cachedEndpointStats instanceof Collection) {
            $this->getData();
        }

        if (! $this->cachedEndpointStats instanceof Collection || $this->cachedEndpointStats->isEmpty()) {
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

        $topEndpoint = $this->cachedEndpointStats->first();
        $topEndpointCount = (int) $topEndpoint->getAttribute('request_count');

        return new HtmlString(
            view('filament.resources.api-request-logs.widgets.chart-description', [
                'leftLabel' => 'Top Endpoint',
                'leftValue' => $topEndpoint->path,
                'leftMeta' => sprintf(
                    '%s %s',
                    $this->formatNumber($topEndpointCount),
                    str('request')->plural($topEndpointCount),
                ),
                'rightGridClass' => 'grid-cols-2',
                'rightColumns' => [
                    [
                        'label' => 'TOTAL',
                        'value' => $this->formatNumber($this->cachedTotalCount),
                        'color' => 'gray',
                    ],
                    [
                        'label' => 'ENDPOINTS',
                        'value' => (string) $this->cachedUniqueEndpoints,
                        'color' => 'gray',
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
                        const value = (context.parsed.x ?? context.parsed.y ?? 0);
                        const pct = context.dataset.percentages?.[context.dataIndex];

                        let formatted;
                        if (value >= 1_000_000) {
                            formatted = (value / 1_000_000).toFixed(1) + 'M';
                        } else if (value >= 1_000) {
                            formatted = (value / 1_000).toFixed(1) + 'k';
                        } else {
                            formatted = value.toString();
                        }

                        const pctStr = pct !== undefined ? ` (${pct}%)` : '';
                        return ` ${formatted} requests${pctStr}`;
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
                    autoSkip: false,
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
