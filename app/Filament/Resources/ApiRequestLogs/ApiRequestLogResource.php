<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiRequestLogs;

use App\Domains\Auth\Models\ApiRequestLog;
use App\Filament\Clusters\ApiCluster;
use App\Filament\Resources\ApiRequestLogs\Pages\ListApiRequestLogs;
use App\Filament\Resources\ApiRequestLogs\Tables\ApiRequestLogsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ApiRequestLogResource extends Resource
{
    protected static ?string $model = ApiRequestLog::class;

    protected static ?string $cluster = ApiCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static bool $isGloballySearchable = false;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $navigationLabel = 'API Requests';

    protected static ?string $modelLabel = 'API Request';

    protected static ?string $pluralModelLabel = 'API Requests';

    protected static ?string $slug = 'requests';

    protected static ?string $description = 'View and analyze API request logs, response times, and error patterns.';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        if (! config('api.request_logging.enabled')) {
            return false;
        }

        return parent::canAccess();
    }

    public static function table(Table $table): Table
    {
        return ApiRequestLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApiRequestLogs::route('/'),
        ];
    }
}
