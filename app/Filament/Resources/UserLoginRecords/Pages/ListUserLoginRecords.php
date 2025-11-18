<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserLoginRecords\Pages;

use App\Domains\User\Models\UserLoginRecord;
use App\Filament\Resources\UserLoginRecords\UserLoginRecordResource;
use App\Filament\Resources\UserLoginRecords\Widgets\DateRangeFilterWidget;
use App\Filament\Resources\UserLoginRecords\Widgets\LoginRecordsStatsWidget;
use App\Filament\Resources\UserLoginRecords\Widgets\LoginsBySegmentChartWidget;
use App\Filament\Resources\UserLoginRecords\Widgets\LoginTrendsChartWidget;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class ListUserLoginRecords extends ListRecords
{
    protected static string $resource = UserLoginRecordResource::class;

    protected ?string $subheading = 'Authentication activity and login insights';

    public ?string $tableStartDate = null;

    public ?string $tableEndDate = null;

    public function mount(): void
    {
        parent::mount();

        $this->tableStartDate = now()->subDays(29)->startOfDay()->toDateTimeString();
        $this->tableEndDate = now()->endOfDay()->toDateTimeString();
    }

    #[On(DateRangeFilterWidget::EVENT_DATE_RANGE_UPDATED)]
    public function updateTableDateRange(string $startDate, string $endDate): void
    {
        $this->tableStartDate = $startDate;
        $this->tableEndDate = $endDate;

        $this->resetPage();
    }

    /** @return Builder<UserLoginRecord>|null */
    public function getTableQuery(): ?Builder
    {
        $query = static::getResource()::getEloquentQuery();

        if ($this->tableStartDate && $this->tableEndDate) {
            $query->whereBetween('logged_in_at', [$this->tableStartDate, $this->tableEndDate]);
        }

        return $query;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            DateRangeFilterWidget::class,
            LoginRecordsStatsWidget::class,
            LoginTrendsChartWidget::class,
            LoginsBySegmentChartWidget::class,
        ];
    }
}
