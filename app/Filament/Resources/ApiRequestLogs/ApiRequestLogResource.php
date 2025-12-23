<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiRequestLogs;

use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\Auth\Models\ApiRequestLog;
use App\Filament\Navigation\AdministrationNavGroup;
use App\Filament\Resources\ApiRequestLogs\Pages\ListApiRequestLogs;
use App\Filament\Resources\ApiRequestLogs\Tables\ApiRequestLogsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ApiRequestLogResource extends Resource
{
    protected static ?string $model = ApiRequestLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $label = 'API Request Logs';

    protected static ?string $slug = 'api-request-logs';

    protected static string|null|UnitEnum $navigationGroup = AdministrationNavGroup::PLATFORM;

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        if (! config('auth.api.enabled') || ! config('auth.api.request_logging.enabled')) {
            return false;
        }

        return auth()->user()->hasPermissionTo(PermissionEnum::MANAGE_ALL);
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
