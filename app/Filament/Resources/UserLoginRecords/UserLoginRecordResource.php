<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserLoginRecords;

use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\User\Models\UserLoginRecord;
use App\Filament\Navigation\AdministrationNavGroup;
use App\Filament\Resources\UserLoginRecords\Pages\ListUserLoginRecords;
use App\Filament\Resources\UserLoginRecords\Tables\UserLoginRecordsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class UserLoginRecordResource extends Resource
{
    protected static ?string $model = UserLoginRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $label = 'Login Records';

    protected static ?string $slug = 'login-records';

    protected static string|null|UnitEnum $navigationGroup = AdministrationNavGroup::PLATFORM;

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()->hasPermissionTo(PermissionEnum::VIEW_LOGIN_RECORDS);
    }

    public static function table(Table $table): Table
    {
        return UserLoginRecordsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserLoginRecords::route('/'),
        ];
    }
}
