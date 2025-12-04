<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\RelationManagers;

use App\Domains\User\Enums\PermissionEnum;
use App\Domains\User\Enums\RoleModificationOriginEnum;
use App\Domains\User\Enums\RoleTypeEnum;
use App\Domains\User\Models\Role;
use App\Domains\User\Models\User;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class RolesRelationManager extends RelationManager
{
    protected static string $relationship = 'roles';

    protected static bool $isLazy = false;

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        /** @var User $ownerRecord */
        return Tab::make('Roles')
            ->icon(Heroicon::OutlinedShieldCheck);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->description(function ($livewire) {
                /** @var User $user */
                $user = $livewire->getOwnerRecord();

                return $user->is_api_user
                    ? new HtmlString('API Users can only be assigned <b>API Integration</b> roles.')
                    : null;
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Role')
                    ->searchable(),
                TextColumn::make('role_type.slug')
                    ->label('Role Type')
                    ->badge()
                    ->searchable()
                    ->sortable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->authorize(PermissionEnum::ASSIGN_ROLES)
                    ->label('Assign Role')
                    ->color('primary')
                    ->outlined()
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->modalHeading('Assign Role to User')
                    ->modalSubmitActionLabel('Add Role')
                    ->attachAnother(false)
                    ->disabled(function (RelationManager $livewire) {
                        /** @var User $user */
                        $user = $livewire->getOwnerRecord();
                        $assignedRoleIds = $user->roles()->pluck('id')->toArray();

                        $availableRoles = Role::query()
                            ->with('role_type')
                            ->whereNotIn('id', $assignedRoleIds)
                            ->when(
                                $user->is_api_user,
                                fn ($query) => $query->whereHas('role_type', fn ($q) => $q->where('slug', RoleTypeEnum::API_INTEGRATION)),
                                fn ($query) => $query->whereHas('role_type', fn ($q) => $q->where('slug', '!=', RoleTypeEnum::API_INTEGRATION))
                            )
                            ->get()
                            ->filter(fn (Role $role) => $role->canBeManaged())
                            ->count();

                        return $availableRoles === 0;
                    })
                    ->tooltip(function (RelationManager $livewire) {
                        /** @var User $user */
                        $user = $livewire->getOwnerRecord();
                        $assignedRoleIds = $user->roles()->pluck('id')->toArray();

                        $availableRoles = Role::query()
                            ->with('role_type')
                            ->whereNotIn('id', $assignedRoleIds)
                            ->when(
                                $user->is_api_user,
                                fn ($query) => $query->whereHas('role_type', fn ($q) => $q->where('slug', RoleTypeEnum::API_INTEGRATION)),
                                fn ($query) => $query->whereHas('role_type', fn ($q) => $q->where('slug', '!=', RoleTypeEnum::API_INTEGRATION))
                            )
                            ->get()
                            ->filter(fn (Role $role) => $role->canBeManaged())
                            ->count();

                        if ($availableRoles === 0) {
                            return 'This user already has all available roles assigned.';
                        }

                        return 'Assign a role to this user';
                    })
                    ->schema(function (RelationManager $livewire) {
                        /** @var User $user */
                        $user = $livewire->getOwnerRecord();
                        $assignedRoleIds = $user->roles()->pluck('id')->toArray();

                        $availableRoles = Role::query()
                            ->with('role_type')
                            ->whereNotIn('id', $assignedRoleIds)
                            ->when(
                                $user->is_api_user,
                                fn ($query) => $query->whereHas('role_type', fn ($q) => $q->where('slug', RoleTypeEnum::API_INTEGRATION)),
                                fn ($query) => $query->whereHas('role_type', fn ($q) => $q->where('slug', '!=', RoleTypeEnum::API_INTEGRATION))
                            )
                            ->get()
                            ->filter(fn (Role $role) => $role->canBeManaged())
                            ->pluck('name', 'id');

                        return [
                            Select::make('recordId')
                                ->label('Role')
                                ->options($availableRoles)
                                ->searchable()
                                ->required()
                                ->helperText(
                                    $user->is_api_user ?
                                        new HtmlString('Only <b>API Integration</b> roles can be assigned to API users.')
                                        : null
                                ),
                        ];
                    })
                    ->action(function (array $data, RelationManager $livewire, AttachAction $action): void {
                        if (! isset($data['recordId'])) {
                            return;
                        }

                        /** @var User $user */
                        $user = $livewire->getOwnerRecord();
                        $role = Role::find($data['recordId']);

                        if (! $role) {
                            return;
                        }

                        // Validate that API roles can only be assigned to API users and vice versa
                        if ($role->role_type->slug === RoleTypeEnum::API_INTEGRATION && ! $user->is_api_user) {
                            Notification::make()
                                ->title('Invalid role assignment')
                                ->body('API Integration roles can only be assigned to API users.')
                                ->danger()
                                ->send();

                            $action->halt();

                            return;
                        }

                        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::UI_ACTION);
                    })
                    ->successNotificationTitle('Role assigned'),
            ])
            ->recordUrl(fn (Role $record) => RoleResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                DetachAction::make()
                    ->label('Remove')
                    ->modalHeading('Remove Role')
                    ->visible(fn (Role $record) => $record->canBeManaged())
                    ->modalDescription(fn (Role $record) => 'Are you sure you want to remove the ' . $record->name . ' role from this user?')
                    ->modalSubmitActionLabel('Remove Role')
                    ->action(function (Role $record, RelationManager $livewire): void {
                        /** @var User $user */
                        $user = $livewire->getOwnerRecord();
                        $user->removeRoleWithAudit($record, RoleModificationOriginEnum::UI_ACTION);
                    })
                    ->successNotificationTitle('Role removed'),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]));
    }
}
