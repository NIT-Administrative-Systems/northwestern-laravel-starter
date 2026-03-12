<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles;

use App\Domains\Auth\Models\Role;
use App\Filament\Navigation\AdministrationNavGroup;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Pages\ViewRole;
use App\Filament\Resources\Roles\RelationManagers\UsersRelationManager;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Filament\Resources\Roles\Tables\RolesTable;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|null|UnitEnum $navigationGroup = AdministrationNavGroup::UserManagement;

    protected static ?int $navigationSort = 2;

    protected static ?string $description = 'Define roles and assign permissions to control user access.';

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    /** @return array<int, class-string> */
    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
        ];
    }

    /** @return array<string, PageRegistration> */
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
        return number_format(Cache::flexible('nav_badge_roles_count', [30, 60], fn () => static::getModel()::count()));
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Role $record */
        $details = [
            'Type' => $record->role_type->slug->getLabel(),
        ];

        if (property_exists($record, 'users_count') && $record->users_count !== null) {
            $details['Users'] = $record->users_count . ' assigned';
        }

        $permissionCount = $record->permissions_count;
        if ($permissionCount > 0) {
            $details['Permissions'] = $permissionCount . ' permission' . ($permissionCount !== 1 ? 's' : '');
        }

        return $details;
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'role_type.label',
        ];
    }

    /** @return Builder<Role> */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        /** @var Builder<Role> $query */
        $query = parent::getGlobalSearchEloquentQuery();

        return $query
            ->with(['role_type'])
            ->withCount(['users', 'permissions']);
    }

    /** @return Builder<Role> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Role> $query */
        $query = parent::getEloquentQuery();

        return $query
            ->with(['role_type', 'permissions'])
            ->withCount(['users', 'permissions']);
    }

    /** @return Builder<Role> */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        /** @var Builder<Role> $query */
        $query = parent::getRecordRouteBindingEloquentQuery();

        return $query
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
