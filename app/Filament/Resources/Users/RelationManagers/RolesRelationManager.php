<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\RelationManagers;

use App\Domains\Auth\Enums\RoleModificationOrigin;
use App\Domains\Auth\Enums\RoleTypeEnum;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

class RolesRelationManager extends RelationManager
{
    protected static string $relationship = 'roles';

    protected static bool $isLazy = false;

    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Get roles available for assignment to the owner user.
     *
     * Results are cached within the request to avoid duplicate queries
     * across disabled(), tooltip(), and schema() callbacks.
     *
     * @return Collection<int, Role>
     */
    private function getAvailableRolesForAssignment(): Collection
    {
        return once(function () {
            /** @var User $user */
            $user = $this->getOwnerRecord();
            $assignedRoleIds = $user->roles()->pluck('id')->toArray();

            return Role::assignable()
                ->with('role_type')
                ->whereNotIn('id', $assignedRoleIds)
                ->when(
                    $user->is_api_user,
                    fn ($query) => $query->whereHas('role_type', fn (\Illuminate\Contracts\Database\Query\Builder $q) => $q->where('slug', RoleTypeEnum::ApiIntegration)),
                    fn ($query) => $query->whereHas('role_type', fn (\Illuminate\Contracts\Database\Query\Builder $q) => $q->where('slug', '!=', RoleTypeEnum::ApiIntegration))
                )
                ->get()
                ->filter(fn (Role $role) => Gate::allows('attachUser', $role));
        });
    }

    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
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
                    ->authorize(SystemPermission::AssignRoles)
                    ->label('Assign Role')
                    ->color('primary')
                    ->outlined()
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->modalHeading('Assign Role to User')
                    ->modalSubmitActionLabel('Add Role')
                    ->attachAnother(false)
                    ->disabled(fn () => $this->getAvailableRolesForAssignment()->isEmpty())
                    ->tooltip(
                        fn () => $this->getAvailableRolesForAssignment()->isEmpty()
                        ? 'This user already has all available roles assigned.'
                        : 'Assign a role to this user'
                    )
                    ->schema(function () {
                        /** @var User $user */
                        $user = $this->getOwnerRecord();

                        return [
                            Select::make('recordId')
                                ->label('Role')
                                ->options($this->getAvailableRolesForAssignment()->pluck('name', 'id'))
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

                        abort_if($role->isAssignmentLocked(), 403, 'This role is assignment-locked and cannot be assigned through the UI.');
                        abort_unless(Gate::allows('attachUser', $role), 403, 'You are not authorized to assign this role.');

                        // Validate that API roles can only be assigned to API users and vice versa
                        if ($role->role_type->slug === RoleTypeEnum::ApiIntegration && ! $user->is_api_user) {
                            Notification::make()
                                ->title('Invalid role assignment')
                                ->body('API Integration roles can only be assigned to API users.')
                                ->danger()
                                ->send();

                            $action->halt();

                            return;
                        }

                        $user->assignRoleWithAudit($role, RoleModificationOrigin::UiAction);
                    })
                    ->successNotificationTitle('Role assigned'),
            ])
            ->recordUrl(fn (Role $record) => route('filament.administration.resources.roles.view', ['record' => $record]))
            ->recordActions([
                DetachAction::make()
                    ->label('Remove')
                    ->modalHeading('Remove Role')
                    ->authorize(fn (Role $record) => ! $record->isAssignmentLocked() && Gate::allows('detachUser', $record))
                    ->modalDescription(fn (Role $record) => 'Are you sure you want to remove the ' . $record->name . ' role from this user?')
                    ->modalSubmitActionLabel('Remove Role')
                    ->action(function (Role $record, RelationManager $livewire): void {
                        abort_if($record->isAssignmentLocked(), 403, 'This role is assignment-locked and cannot be removed through the UI.');
                        abort_unless(Gate::allows('detachUser', $record), 403, 'You are not authorized to remove this role.');

                        /** @var User $user */
                        $user = $livewire->getOwnerRecord();
                        $user->removeRoleWithAudit($record, RoleModificationOrigin::UiAction);
                    })
                    ->successNotificationTitle('Role removed'),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]));
    }
}
