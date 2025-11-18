<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles;

use App\Domains\User\Models\Role;
use App\Filament\Navigation\AdministrationNavGroup;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Pages\ViewRole;
use App\Filament\Resources\Roles\RelationManagers\UsersRelationManager;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Filament\Resources\Roles\Tables\RolesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|null|UnitEnum $navigationGroup = AdministrationNavGroup::USER_MANAGEMENT;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'view' => ViewRole::route('/{record}'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return number_format(static::getModel()::count());
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Role $record */
        $details = [
            'Type' => $record->role_type->slug->getLabel(),
        ];

        if (isset($record->users_count)) {
            $details['Users'] = $record->users_count . ' assigned';
        }

        $permissionCount = $record->permissions_count;
        if ($permissionCount > 0) {
            $details['Permissions'] = $permissionCount . ' permission' . ($permissionCount !== 1 ? 's' : '');
        }

        return $details;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'role_type.label',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['role_type'])
            ->withCount(['users', 'permissions']);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('role_type')
            ->withCount('users');
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
