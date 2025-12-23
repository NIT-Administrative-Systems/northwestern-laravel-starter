<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiRequestLogs\Widgets;

use App\Domains\Auth\Models\ApiRequestLog;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Livewire\Attributes\On;

abstract class BaseApiRequestChartWidget extends ChartWidget
{
    protected ?string $heading = '';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public ?int $userId = null;

    protected ?string $maxHeight = '300px';

    protected ?string $pollingInterval = '10s';

    public function mount(): void
    {
        $now = Carbon::now(auth()->user()->timezone);

        $this->startDate = $now->copy()->subDays(29)->startOfDay()->utc()->toDateTimeString();
        $this->endDate = $now->copy()->endOfDay()->utc()->toDateTimeString();
    }

    #[On(ApiRequestFilterWidget::EVENT_DATE_RANGE_UPDATED)]
    public function updateDateRange(string $startDate, string $endDate): void
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;

        $this->dispatch('$refresh');
    }

    #[On(ApiRequestFilterWidget::EVENT_USER_FILTER_UPDATED)]
    public function updateUserFilter(?int $userId): void
    {
        $this->userId = $userId;

        $this->dispatch('$refresh');
    }

    protected function formatDuration(float $milliseconds): string
    {
        if ($milliseconds >= 1000) {
            $seconds = $milliseconds / 1000;

            return Number::format($seconds, precision: 1) . ' s';
        }

        return Number::format($milliseconds, precision: 0) . ' ms';
    }

    protected function formatNumber(int|float $number): string
    {
        if ($number < 1000) {
            return (string) $number;
        }

        return Number::abbreviate($number, precision: 1);
    }

    protected function baseQuery(): Builder
    {
        return ApiRequestLog::query()
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->when(
                $this->userId,
                fn (Builder $query, int $userId) => $query->where('user_id', $userId),
            );
    }
}
