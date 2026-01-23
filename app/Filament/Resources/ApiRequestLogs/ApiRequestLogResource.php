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

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $navigationLabel = 'Activity';

    protected static ?string $label = 'Request';

    protected static ?string $pluralLabel = 'Requests';

    protected static ?string $slug = 'activity';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        if (! config('auth.api.request_logging.enabled')) {
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
