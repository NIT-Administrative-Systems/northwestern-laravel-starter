<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiRequestLogs\Widgets;

use Carbon\Carbon;
use Illuminate\Support\HtmlString;

class ApiRequestsByStatusChartWidget extends BaseApiRequestChartWidget
{
    public function getDescription(): HtmlString|string|null
    {
        if (! $this->startDate || ! $this->endDate) {
            return null;
        }

        $statusCounts = $this->baseQuery()
            ->selectRaw("
                CASE
                    WHEN status_code >= 100 AND status_code < 400 THEN '1xx-3xx'
                    WHEN status_code >= 400 AND status_code < 500 THEN '4xx'
                    WHEN status_code >= 500 THEN '5xx'
                END as status_range,
                COUNT(*) as count
            ")
            ->groupBy('status_range')
            ->get()
            ->pluck('count', 'status_range');

        return new HtmlString(
            view('filament.resources.api-request-logs.widgets.chart-description', [
                'leftLabel' => 'Requests',
                'leftValue' => $this->formatNumber($statusCounts->sum()),
                'rightGridClass' => 'grid-cols-3',
                'rightColumns' => [
                    [
                        'label' => '1xx-3xx',
                        'value' => $this->formatNumber($statusCounts->get('1xx-3xx', 0)),
                        'color' => 'success',
                    ],
                    [
                        'label' => '4xx',
                        'value' => $this->formatNumber($statusCounts->get('4xx', 0)),
                        'color' => 'warning',
                    ],
                    [
                        'label' => '5xx',
                        'value' => $this->formatNumber($statusCounts->get('5xx', 0)),
                        'color' => 'danger',
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
        $isSingleDay = $startInUserTz->isSameDay($endInUserTz);

        if ($isSingleDay) {
            $requestsPerPeriodPerStatus = $this->baseQuery()
                ->selectRaw("
                    CASE
                        WHEN status_code >= 100 AND status_code < 400 THEN '1xx-3xx'
                        WHEN status_code >= 400 AND status_code < 500 THEN '4xx'
                        WHEN status_code >= 500 THEN '5xx'
                    END as status_range,
                    EXTRACT(HOUR FROM created_at AT TIME ZONE 'UTC' AT TIME ZONE ?) as hour,
                    COUNT(*) as count
                ", [$timezone])
                ->groupBy('status_range', 'hour')
                ->orderBy('hour')
                ->get();

            $labels = [];
            $periodMap = [];

            foreach (range(0, 23) as $hour) {
                $labels[] = date('g A', mktime($hour, 0));
                $periodMap[(string) $hour] = array_key_last($labels);
            }
        } else {
            // Daily grouping for multi-day range
            $requestsPerPeriodPerStatus = $this->baseQuery()
                ->selectRaw("
                    CASE
                        WHEN status_code >= 100 AND status_code < 400 THEN '1xx-3xx'
                        WHEN status_code >= 400 AND status_code < 500 THEN '4xx'
                        WHEN status_code >= 500 THEN '5xx'
                    END as status_range,
                    DATE(created_at AT TIME ZONE 'UTC' AT TIME ZONE ?) as date,
                    COUNT(*) as count
                ", [$timezone])
                ->groupBy('status_range', 'date')
                ->orderBy('date')
                ->get();

            $labels = [];
            $periodMap = [];
            $current = $startInUserTz->copy()->startOfDay();

            while ($current->lte($endInUserTz)) {
                $dateKey = $current->format('Y-m-d');
                $labels[] = $current->format('M d');
                $periodMap[$dateKey] = array_key_last($labels);
                $current->addDay();
            }
        }

        $successData = array_fill(0, count($labels), 0);
        $clientErrorData = array_fill(0, count($labels), 0);
        $serverErrorData = array_fill(0, count($labels), 0);

        $seriesByStatus = [
            '1xx-3xx' => &$successData,
            '4xx' => &$clientErrorData,
            '5xx' => &$serverErrorData,
        ];

        /**
         * @var object{
         *     status_range: string,
         *     hour?: int,
         *     date?: string,
         *     count: int
         * } $record
         */
        foreach ($requestsPerPeriodPerStatus as $record) {
            $key = $isSingleDay ? (string) $record->hour : $record->date;

            if (! isset($periodMap[$key])) {
                continue;
            }

            $index = $periodMap[$key];

            if (isset($seriesByStatus[$record->status_range])) {
                $seriesByStatus[$record->status_range][$index] = (int) $record->count;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => '1xx-3xx Success',
                    'data' => $successData,
                    'backgroundColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 0,
                    'borderRadius' => 2,
                ],
                [
                    'label' => '4xx Client Error',
                    'data' => $clientErrorData,
                    'backgroundColor' => 'rgb(234, 179, 8)',
                    'borderWidth' => 0,
                    'borderRadius' => 2,
                ],
                [
                    'label' => '5xx Server Error',
                    'data' => $serverErrorData,
                    'backgroundColor' => 'rgb(239, 68, 68)',
                    'borderWidth' => 0,
                    'borderRadius' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'stacked' => true,
                    'grid' => [
                        'display' => false,
                    ],
                ],
                'y' => [
                    'stacked' => true,
                    'beginAtZero' => true,
                    'grid' => [
                        'display' => false,
                    ],
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}
