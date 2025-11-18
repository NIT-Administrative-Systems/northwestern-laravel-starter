<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiRequestLogs\Widgets;

use App\Domains\User\Models\ApiRequestLog;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Illuminate\Support\HtmlString;

class ApiRequestDurationChartWidget extends BaseApiRequestChartWidget
{
    public function getDescription(): HtmlString|string|null
    {
        if (! $this->startDate || ! $this->endDate) {
            return null;
        }

        $query = ApiRequestLog::query()
            ->whereBetween('created_at', [$this->startDate, $this->endDate]);

        if ($this->userId) {
            $query->where('user_id', $this->userId);
        }

        /** @var object{
         *     p50: float|null,
         *     p95: float|null,
         *     avg_duration: float|null,
         *     max_duration: float|null
         * } $stats
         */
        $stats = $query->selectRaw('
                PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY duration_ms) as p50,
                PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY duration_ms) as p95,
                AVG(duration_ms) as avg_duration,
                MAX(duration_ms) as max_duration
            ')
            ->first();

        if (! $stats) {
            return null;
        }

        $p50 = (float) $stats->p50;
        $p95 = (float) $stats->p95;
        $avgDuration = (float) $stats->avg_duration;
        $maxDuration = (float) $stats->max_duration;

        return new HtmlString(
            view('filament.resources.api-request-logs.widgets.chart-description', [
                'leftLabel' => 'Duration (P50 - P95)',
                'leftValue' => $this->formatDuration($p50) . ' - ' . $this->formatDuration($p95),
                'rightGridClass' => 'grid-cols-2',
                'rightColumns' => [
                    [
                        'label' => 'AVERAGE',
                        'value' => $this->formatDuration($avgDuration),
                        'color' => 'success',
                    ],
                    [
                        'label' => 'MAX',
                        'value' => $this->formatDuration($maxDuration),
                        'color' => 'warning',
                    ],
                ],
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

        $timezone = auth()->user()->timezone;
        $startInUserTz = Carbon::parse($this->startDate, 'UTC')->setTimezone($timezone);
        $endInUserTz = Carbon::parse($this->endDate, 'UTC')->setTimezone($timezone);

        // Determine if this is a single day view (hourly) or multi-day view (daily)
        $isSingleDay = $startInUserTz->isSameDay($endInUserTz);

        if ($isSingleDay) {
            // Hourly grouping for single day
            $durationsPerPeriod = $this->baseQuery()->selectRaw("
                    EXTRACT(HOUR FROM created_at AT TIME ZONE 'UTC' AT TIME ZONE ?) as hour,
                    AVG(duration_ms) as avg_duration,
                    MAX(duration_ms) as max_duration
                ", [$timezone])
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();

            $labels = [];
            $periodMap = [];

            for ($hour = 0; $hour < 24; $hour++) {
                $labels[] = date('g A', mktime($hour, 0));
                $periodMap[(string) $hour] = count($labels) - 1;
            }
        } else {
            $durationsPerPeriod = $this->baseQuery()->selectRaw("
                    DATE(created_at AT TIME ZONE 'UTC' AT TIME ZONE ?) as date,
                    AVG(duration_ms) as avg_duration,
                    MAX(duration_ms) as max_duration
                ", [$timezone])
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            $labels = [];
            $periodMap = [];
            $current = $startInUserTz->copy()->startOfDay();

            while ($current->lte($endInUserTz)) {
                $dateKey = $current->format('Y-m-d');
                $labels[] = $current->format('M d');
                $periodMap[$dateKey] = count($labels) - 1;
                $current->addDay();
            }
        }

        // Initialize data arrays with 0 instead of null for better visibility
        $avgData = array_fill(0, count($labels), 0);
        $maxData = array_fill(0, count($labels), 0);

        /** @var object{
         *     hour?: int,
         *     date?: string,
         *     avg_duration: float|null,
         *     max_duration: float|null
         * } $record
         */
        foreach ($durationsPerPeriod as $record) {
            $key = $isSingleDay ? (string) $record->hour : $record->date;

            if (isset($periodMap[$key])) {
                $index = $periodMap[$key];
                $avgData[$index] = round((float) $record->avg_duration, 2);
                $maxData[$index] = (int) $record->max_duration;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Average Duration',
                    'data' => $avgData,
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 2,
                    'pointHoverRadius' => 5,
                    'spanGaps' => false,
                ],
                [
                    'label' => 'Max Duration',
                    'data' => $maxData,
                    'borderColor' => 'rgb(234, 179, 8)',
                    'backgroundColor' => 'rgba(234, 179, 8, 0.1)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 2,
                    'pointHoverRadius' => 5,
                    'spanGaps' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            responsive: true,
            maintainAspectRatio: false,

            interaction: {
                mode: 'index',
                intersect: false,
            },

            plugins: {
                legend: {
                    display: false,
                },

                tooltip: {
                    mode: 'index',
                    intersect: false,

                    callbacks: {
                        label: function (context) {
                            const label = context.dataset.label || '';
                            const value = context.parsed.y ?? 0;

                            let formatted;
                            if (value >= 1000) {
                                formatted = (value / 1000).toFixed(1) + ' s';
                            } else {
                                formatted = Math.round(value) + ' ms';
                            }

                            return `${label}: ${formatted}`;
                        }
                    }
                }
            },

            scales: {
                x: {
                    grid: {
                        display: false,
                    },
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        display: false,
                    },
                    title: {
                        display: true,
                        text: 'Duration (ms)',
                        font: {
                            size: 11,
                            weight: '500',
                        },
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
            },

            elements: {
                line: {
                    tension: 0.4,
                },
            },
        }
    JS);
    }
}
