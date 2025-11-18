<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Domains\User\Models\User;
use App\Filament\Navigation\AdministrationNavGroup;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\ApiRequestLogsRelationManager;
use App\Filament\Resources\Users\RelationManagers\ApiTokensRelationManager;
use App\Filament\Resources\Users\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\Users\RelationManagers\LoginRecordsRelationManager;
use App\Filament\Resources\Users\RelationManagers\RolesRelationManager;
use App\Filament\Resources\Users\Tables\UsersTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|null|UnitEnum $navigationGroup = AdministrationNavGroup::USER_MANAGEMENT;

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RolesRelationManager::class,
            AuditsRelationManager::class,
            LoginRecordsRelationManager::class,
            ApiTokensRelationManager::class,
            ApiRequestLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return number_format(static::getModel()::count());
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        /** @var User $record */
        return sprintf('%s (%s)', $record->clerical_name, $record->username);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var User $record */
        return [
            'Email' => $record->email ?: '—',
            'Affiliation' => $record->primary_affiliation?->getLabel() ?: '—',
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'username',
            'first_name',
            'last_name',
            'email',
            'employee_id',
            'hr_employee_id',
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
