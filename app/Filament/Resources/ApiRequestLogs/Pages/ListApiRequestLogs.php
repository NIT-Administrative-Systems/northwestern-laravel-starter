<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiRequestLogs\Pages;

use App\Domains\Core\Services\ApiRouteInspector;
use App\Domains\User\Models\ApiRequestLog;
use App\Filament\Resources\ApiRequestLogs\ApiRequestLogResource;
use App\Filament\Resources\ApiRequestLogs\Widgets\ApiRequestDurationChartWidget;
use App\Filament\Resources\ApiRequestLogs\Widgets\ApiRequestFilterWidget;
use App\Filament\Resources\ApiRequestLogs\Widgets\ApiRequestsByStatusChartWidget;
use App\Filament\Resources\ApiRequestLogs\Widgets\NoProtectedApiRoutesBanner;
use App\Filament\Resources\ApiRequestLogs\Widgets\SlowestApiRequestsByEndpointChartWidget;
use App\Filament\Resources\ApiRequestLogs\Widgets\TopApiRequestsByEndpointChartWidget;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class ListApiRequestLogs extends ListRecords
{
    protected static string $resource = ApiRequestLogResource::class;

    protected ?string $subheading = 'Inbound API activity and request metrics';

    public ?string $tableStartDate = null;

    public ?string $tableEndDate = null;

    public ?int $tableUserId = null;

    public function mount(): void
    {
        parent::mount();

        $this->tableStartDate = now()->subDays(29)->startOfDay()->toDateTimeString();
        $this->tableEndDate = now()->endOfDay()->toDateTimeString();
    }

    #[On(ApiRequestFilterWidget::EVENT_DATE_RANGE_UPDATED)]
    public function updateTableDateRange(string $startDate, string $endDate): void
    {
        $this->tableStartDate = $startDate;
        $this->tableEndDate = $endDate;

        $this->resetPage();
    }

    #[On(ApiRequestFilterWidget::EVENT_USER_FILTER_UPDATED)]
    public function updateTableUserFilter(?int $userId): void
    {
        $this->tableUserId = $userId;

        $this->resetPage();
    }

    /** @return Builder<ApiRequestLog>|null */
    public function getTableQuery(): ?Builder
    {
        $query = static::getResource()::getEloquentQuery();

        if ($this->tableStartDate && $this->tableEndDate) {
            $query->whereBetween('created_at', [$this->tableStartDate, $this->tableEndDate]);
        }

        if ($this->tableUserId) {
            $query->where('user_id', $this->tableUserId);
        }

        return $query;
    }

    protected function getHeaderWidgets(): array
    {
        $widgets = [
            ApiRequestFilterWidget::class,
            ApiRequestsByStatusChartWidget::class,
            ApiRequestDurationChartWidget::class,
            TopApiRequestsByEndpointChartWidget::class,
            SlowestApiRequestsByEndpointChartWidget::class,
        ];

        if (! app(ApiRouteInspector::class)->hasProtectedRoutes()) {
            array_unshift($widgets, NoProtectedApiRoutesBanner::class);
        }

        return $widgets;
    }
}
