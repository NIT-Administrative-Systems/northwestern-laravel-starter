<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Users;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Auth\Enums\AuthType;
use App\Domains\User\Models\User;
use App\Filament\Administration\Navigation\AdministrationNavGroup;
use App\Filament\Administration\Resources\Users\Pages\ListUsers;
use App\Filament\Administration\Resources\Users\Pages\ViewUser;
use App\Filament\Administration\Resources\Users\RelationManagers\ApiRequestLogsRelationManager;
use App\Filament\Administration\Resources\Users\RelationManagers\AuditsRelationManager;
use App\Filament\Administration\Resources\Users\RelationManagers\ConnectedApplicationsRelationManager;
use App\Filament\Administration\Resources\Users\RelationManagers\LoginRecordsRelationManager;
use App\Filament\Administration\Resources\Users\RelationManagers\PersonalAccessTokensRelationManager;
use App\Filament\Administration\Resources\Users\RelationManagers\RoleActivityRelationManager;
use App\Filament\Administration\Resources\Users\RelationManagers\RolesRelationManager;
use App\Filament\Administration\Resources\Users\RelationManagers\ServiceClientsRelationManager;
use App\Filament\Administration\Resources\Users\Tables\UsersTable;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static bool $isGloballySearchable = true;

    protected static int $globalSearchResultsLimit = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|null|UnitEnum $navigationGroup = AdministrationNavGroup::UserManagement;

    protected static ?int $navigationSort = 1;

    protected static ?string $description = 'Manage users, their roles, and authentication settings.';

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    /** @return array<int, class-string> */
    public static function getRelations(): array
    {
        return [
            RolesRelationManager::class,
            RoleActivityRelationManager::class,
            AuditsRelationManager::class,
            LoginRecordsRelationManager::class,
            PersonalAccessTokensRelationManager::class,
            ConnectedApplicationsRelationManager::class,
            ServiceClientsRelationManager::class,
            ApiRequestLogsRelationManager::class,
        ];
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $scope = self::seesEveryone() ? 'all' : 'api';

        return number_format(Cache::flexible("nav_badge_users_count:{$scope}", [30, 60], fn () => static::getEloquentQuery()->count()));
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        /** @var User $record */
        return sprintf('%s (%s)', $record->clerical_name, $record->username);
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var User $record */
        return [
            'Email' => $record->email ?: '—',
            'Affiliation' => $record->primary_affiliation?->getLabel() ?: '—',
        ];
    }

    /** @return list<string> */
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

    /**
     * Everyone for View Users; only API users for someone who manages API access without it.
     *
     * @return Builder<User>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<User> $query */
        $query = parent::getEloquentQuery();

        return self::seesEveryone() ? $query : $query->where('auth_type', AuthType::API);
    }

    /** @return Builder<User> */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        /** @var Builder<User> $query */
        $query = parent::getRecordRouteBindingEloquentQuery();

        return $query
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    private static function seesEveryone(): bool
    {
        return (bool) auth()->user()?->can(SystemPermission::ViewUsers);
    }
}
