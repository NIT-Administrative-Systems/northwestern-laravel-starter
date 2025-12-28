<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\RelationManagers;

use App\Domains\Auth\Enums\AuthTypeEnum;
use App\Domains\User\Models\User;
use App\Filament\Resources\ApiRequestLogs\Tables\ApiRequestLogsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * @property User $ownerRecord
 */
class ApiRequestLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'api_request_logs';

    protected static ?string $title = 'API Request Logs';

    protected static ?string $recordTitleAttribute = 'id';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! config('auth.api.request_logging.enabled')) {
            return false;
        }

        /** @var User $ownerRecord */
        return $ownerRecord->auth_type === AuthTypeEnum::API;
    }

    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        return Tab::make('API Request Logs')
            ->icon(Heroicon::OutlinedClipboardDocumentList);
    }

    public function table(Table $table): Table
    {
        return ApiRequestLogsTable::configure($table);
    }
}
