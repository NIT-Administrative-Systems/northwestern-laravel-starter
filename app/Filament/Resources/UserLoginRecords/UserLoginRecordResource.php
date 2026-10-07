<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserLoginRecords;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Models\UserLoginRecord;
use App\Filament\Navigation\AdministrationNavGroup;
use App\Filament\Resources\UserLoginRecords\Pages\ListUserLoginRecords;
use App\Filament\Resources\UserLoginRecords\Tables\UserLoginRecordsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Northwestern\SysDev\Chassis\Formatting\TitleCase;
use UnitEnum;

class UserLoginRecordResource extends Resource
{
    protected static ?string $model = UserLoginRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $modelLabel = 'sign-in record';

    protected static ?string $pluralModelLabel = 'sign-in records';

    protected static ?string $slug = 'login-records';

    protected static string|null|UnitEnum $navigationGroup = AdministrationNavGroup::Platform;

    protected static ?int $navigationSort = 4;

    protected static ?string $description = 'Who signed in, how, and when.';

    // Filament's Str::ucwords would give "Sign-in Records".
    public static function getTitleCaseModelLabel(): string
    {
        return TitleCase::of(static::getModelLabel());
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return TitleCase::of(static::getPluralModelLabel());
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(SystemPermission::ViewLoginRecords);
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
