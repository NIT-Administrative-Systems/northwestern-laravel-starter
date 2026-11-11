<?php

declare(strict_types=1);

namespace App\Filament\Administration\Clusters\ApiCluster\Resources\ApiRequestLogs;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Api\Models\ApiRequestLog;
use App\Filament\Administration\Clusters\ApiCluster;
use App\Filament\Administration\Clusters\ApiCluster\Resources\ApiRequestLogs\Pages\ListApiRequestLogs;
use App\Filament\Administration\Clusters\ApiCluster\Resources\ApiRequestLogs\Tables\ApiRequestLogsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ApiRequestLogResource extends Resource
{
    protected static ?string $model = ApiRequestLog::class;

    protected static ?string $cluster = ApiCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $navigationLabel = 'API Requests';

    protected static ?string $modelLabel = 'API request';

    protected static ?string $pluralModelLabel = 'API requests';

    protected static ?string $slug = 'requests';

    protected static ?int $navigationSort = 2;

    /**
     * canAccess() decides, as it does for the list page, since the policy
     * doesn't know whether the API and request logging are turned on.
     */
    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        if (! config('api.enabled') || ! config('api.request_logging.enabled')) {
            return false;
        }

        return (bool) auth()->user()?->can(SystemPermission::ViewApiRequestLogs);
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
