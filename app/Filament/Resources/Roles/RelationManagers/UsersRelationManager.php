<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\RelationManagers;

use App\Domains\Auth\Enums\AuthTypeEnum;
use App\Domains\Auth\Enums\RoleModificationOriginEnum;
use App\Domains\Auth\Enums\RoleTypeEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Enums\AffiliationEnum;
use App\Domains\User\Models\User;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Assigned Users';

    protected static bool $isLazy = false;

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('username')
            ->columns([
                TextColumn::make('clerical_name')
                    ->sortable(query: function (Builder $query, $direction) {
                        return $query->orderBy('last_name', $direction)->orderBy('first_name', $direction);
                    })
                    ->label('Name'),
                TextColumn::make('username')
                    ->label('NetID')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('primary_affiliation')
                    ->label('Primary Affiliation')
                    ->searchable()
                    ->badge(),
                TextColumn::make('roles.name')
                    ->badge()
                    ->searchable(),
                TextColumn::make('first_name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('employee_id')
                    ->label('Employee ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('hr_employee_id')
                    ->label('myHR Employee ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('timezone')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('auth_type')
                    ->label('Auth Type')
                    ->badge()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('primary_affiliation')
                    ->label('Primary Affiliation')
                    ->options(AffiliationEnum::class)
                    ->multiple(),
                SelectFilter::make('auth_type')
                    ->label('Auth Type')
                    ->options(AuthTypeEnum::class)
                    ->multiple(),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->headerActions([
                AttachAction::make()
                    ->visible(function () {
                        /** @var Role $role */
                        $role = $this->getOwnerRecord();

                        return $role->canBeManaged();
                    })
                    ->label('Assign User')
                    ->color('primary')
                    ->outlined()
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->modalHeading('Assign User to Role')
                    ->modalSubmitActionLabel('Add User')
                    ->attachAnother(false)
                    ->schema(function (RelationManager $livewire) {
                        /** @var Role $role */
                        $role = $livewire->getOwnerRecord();
                        $isApiRole = $role->role_type->slug === RoleTypeEnum::API_INTEGRATION;

                        return [
                            Select::make('recordId')
                                ->label('User')
                                ->helperText($isApiRole
                                    ? new HtmlString('Only API users can be assigned to <b>API Integration</b> roles. Search by username or email.')
                                    : 'Search by name, NetID, or email')
                                ->searchable()
                                ->noSearchResultsMessage('No users match your search, or all matching users already have this role assigned.')
                                ->getSearchResultsUsing(function (string $search, RelationManager $livewire) use ($isApiRole) {
                                    /** @var Role $role */
                                    $role = $livewire->getOwnerRecord();

                                    return User::query()
                                        ->where(function (Builder $query) use ($search) {
                                            $query->searchByName($search)
                                                ->orWhere('username', 'ilike', "%{$search}%")
                                                ->orWhere('email', 'ilike', "%{$search}%");
                                        })
                                        ->when($isApiRole, fn (Builder $query) => $query->api())
                                        ->when(! $isApiRole, fn (Builder $query) => $query->where('auth_type', '!=', AuthTypeEnum::API))
                                        ->whereDoesntHave('roles', function (Builder $query) use ($role) {
                                            $query->where('id', $role->id);
                                        })
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn (User $user) => [
                                            $user->id => sprintf('%s (%s)', $user->clerical_name, $user->username),
                                        ]);
                                })
                                ->getOptionLabelUsing(function ($value) {
                                    $user = User::find($value);

                                    return $user ? sprintf('%s (%s)', $user->clerical_name, $user->username) : null;
                                })
                                ->required(),
                        ];
                    })
                    ->action(function (array $data, RelationManager $livewire, AttachAction $action): void {
                        $user = User::find($data['recordId']);
                        /** @var Role $role */
                        $role = $livewire->getOwnerRecord();

                        if (! $user) {
                            return;
                        }

                        if ($role->role_type->slug === RoleTypeEnum::API_INTEGRATION && ! $user->is_api_user) {
                            Notification::make()
                                ->title('Invalid user assignment')
                                ->body('API Integration roles can only be assigned to API users.')
                                ->danger()
                                ->send();

                            $action->halt();

                            return;
                        }

                        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::UI_ACTION);
                    })
                    ->successNotificationTitle('User assigned'),
            ])
            ->recordUrl(fn (User $record) => UserResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                DetachAction::make()
                    ->visible(function () {
                        /** @var Role $role */
                        $role = $this->getOwnerRecord();

                        return $role->canBeManaged();
                    })
                    ->button()
                    ->outlined()
                    ->size(Size::ExtraSmall)
                    ->label('Remove')
                    ->modalHeading('Remove Role')
                    ->modalDescription(function (User $record, RelationManager $livewire) {
                        /** @var Role $role */
                        $role = $livewire->getOwnerRecord();

                        return 'Are you sure you want to remove the ' . $role->name . ' role from ' . $record->clerical_name . '?';
                    })
                    ->modalSubmitActionLabel('Remove Role')
                    ->action(function (User $record, RelationManager $livewire): void {
                        /** @var Role $role */
                        $role = $livewire->getOwnerRecord();
                        $record->removeRoleWithAudit($role, RoleModificationOriginEnum::UI_ACTION);
                    })
                    ->successNotificationTitle('Role removed'),
            ]);
    }
}
