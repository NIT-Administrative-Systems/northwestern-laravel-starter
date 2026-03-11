<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiRequestLogs\Widgets;

use App\Domains\Auth\Models\ApiRequestLog;
use App\Domains\Core\Database\DatabaseExpressions;
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
        $stats = $this->computeSummaryStats($query);

        if (! $stats) {
            return null;
        }

        $p50 = (float) $stats->p50;
        $p95 = (float) $stats->p95;
        $avgDuration = (float) $stats->avg_duration;
        $threshold = (int) config('api.request_logging.slow_request_threshold_ms');

        $thresholdColor = $p95 > $threshold ? 'danger' : 'success';

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
                        'label' => 'P95',
                        'value' => $this->formatDuration($p95),
                        'color' => $thresholdColor,
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

        $extractHour = DatabaseExpressions::extractHour('created_at', $timezone);
        $dateInTz = DatabaseExpressions::dateInTimezone('created_at', $timezone);
        $p95 = DatabaseExpressions::percentile('duration_ms', 0.95);
        $nativePercentile = DatabaseExpressions::supportsNativePercentile();

        if ($isSingleDay) {
            $selectParts = [
                "{$extractHour['sql']} as hour",
                'AVG(duration_ms) as avg_duration',
            ];
            $bindings = $extractHour['bindings'];

            if ($nativePercentile) {
                $selectParts[] = "{$p95['sql']} as p95_duration";
            }

            $durationsPerPeriod = $this->baseQuery()
                ->selectRaw(implode(', ', $selectParts), $bindings)
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();

            if (! $nativePercentile) {
                $this->computeGroupedP95($durationsPerPeriod, 'hour', $extractHour);
            }

            $labels = [];
            $periodMap = [];

            for ($hour = 0; $hour < 24; $hour++) {
                $labels[] = date('g A', (int) mktime($hour, 0));
                $periodMap[(string) $hour] = count($labels) - 1;
            }
        } else {
            $selectParts = [
                "{$dateInTz['sql']} as date",
                'AVG(duration_ms) as avg_duration',
            ];
            $bindings = $dateInTz['bindings'];

            if ($nativePercentile) {
                $selectParts[] = "{$p95['sql']} as p95_duration";
            }

            $durationsPerPeriod = $this->baseQuery()
                ->selectRaw(implode(', ', $selectParts), $bindings)
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            if (! $nativePercentile) {
                $this->computeGroupedP95($durationsPerPeriod, 'date', $dateInTz);
            }

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

        $avgData = array_fill(0, count($labels), 0);
        $p95Data = array_fill(0, count($labels), 0);

        /** @var object{
         *     hour?: int,
         *     date?: string,
         *     avg_duration: float|null,
         *     p95_duration: float|null
         * } $record
         */
        foreach ($durationsPerPeriod as $record) {
            $key = $isSingleDay ? (string) $record->hour : $record->date;

            if (isset($periodMap[$key])) {
                $index = $periodMap[$key];
                $avgData[$index] = round((float) $record->avg_duration, 2);
                $p95Data[$index] = round((float) ($record->p95_duration ?? 0), 2);
            }
        }

        $threshold = (int) config('api.request_logging.slow_request_threshold_ms');
        $thresholdData = array_fill(0, count($labels), $threshold);

        $p95PointColors = array_map(
            fn (float $v) => $v > $threshold ? 'rgb(239, 68, 68)' : 'rgb(59, 130, 246)',
            $p95Data
        );

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
                    'label' => 'P95 Duration',
                    'data' => $p95Data,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.05)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 3,
                    'pointHoverRadius' => 6,
                    'pointBackgroundColor' => $p95PointColors,
                    'spanGaps' => false,
                ],
                [
                    'label' => 'Slow Threshold',
                    'data' => $thresholdData,
                    'borderColor' => 'rgba(239, 68, 68, 0.4)',
                    'borderDash' => [6, 4],
                    'borderWidth' => 2,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 0,
                    'fill' => false,
                    'tension' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * Compute summary stats (P50, P95, AVG, MAX), falling back to PHP for non-PostgreSQL drivers.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ApiRequestLog>  $query
     * @return object{p50: float|null, p95: float|null, avg_duration: float|null, max_duration: float|null}|null
     */
    private function computeSummaryStats(\Illuminate\Database\Eloquent\Builder $query): ?object
    {
        if (DatabaseExpressions::supportsNativePercentile()) {
            $p50Expr = DatabaseExpressions::percentile('duration_ms', 0.5);
            $p95Expr = DatabaseExpressions::percentile('duration_ms', 0.95);

            /** @var object{p50: float|null, p95: float|null, avg_duration: float|null, max_duration: float|null}|null */
            return $query->selectRaw(
                "{$p50Expr['sql']} as p50, {$p95Expr['sql']} as p95, AVG(duration_ms) as avg_duration, MAX(duration_ms) as max_duration"
            )->first();
        }

        // For MySQL/SQLite: fetch all durations in a single query and compute in PHP
        $durations = $query->pluck('duration_ms');

        if ($durations->isEmpty()) {
            return null;
        }

        $percentiles = DatabaseExpressions::computeMultiplePercentilesInPhp($durations, [0.5, 0.95]);

        return (object) [
            'p50' => $percentiles['0.5'],
            'p95' => $percentiles['0.95'],
            'avg_duration' => $durations->avg(),
            'max_duration' => $durations->max(),
        ];
    }

    /**
     * Compute P95 in PHP for grouped results (MySQL/SQLite fallback).
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, ApiRequestLog>  $records
     * @param  array{sql: string, bindings: array<int, string>}  $periodExpr
     */
    private function computeGroupedP95(\Illuminate\Database\Eloquent\Collection $records, string $groupKey, array $periodExpr): void
    {
        $allRows = $this->baseQuery()
            ->selectRaw("{$periodExpr['sql']} as period_key, duration_ms", $periodExpr['bindings'])
            ->get();

        $grouped = $allRows->groupBy('period_key');

        foreach ($records as $record) {
            $key = (string) $record->{$groupKey};
            $values = $grouped->get($key, collect())->pluck('duration_ms');
            $record->setAttribute('p95_duration', DatabaseExpressions::computePercentileInPhp($values, 0.95));
        }
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

                            // Skip the threshold line in tooltip
                            if (context.dataset.borderDash) {
                                return null;
                            }

                            let formatted;
                            if (value >= 1000) {
                                formatted = (value / 1000).toFixed(1) + ' s';
                            } else {
                                formatted = Math.round(value) + ' ms';
                            }

                            return `${label}: ${formatted}`;
                        },

                        filter: function (item) {
                            return !(item.dataset && item.dataset.borderDash);
                        },
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
