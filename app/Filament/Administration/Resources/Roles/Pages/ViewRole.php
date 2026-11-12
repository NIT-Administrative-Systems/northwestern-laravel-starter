<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Roles\Pages;

use App\Domains\Access\Enums\PermissionScope;
use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Models\Permission;
use App\Domains\Access\Models\Role;
use App\Filament\Administration\Resources\Roles\RoleResource;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Role $record
 */
class ViewRole extends ViewRecord
{
    protected static string $resource = RoleResource::class;

    protected static ?string $navigationLabel = 'Details';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInformationCircle;

    /**
     * What the role grants, read at a glance, instead of the edit form disabled.
     */
    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            ...$this->notices(),
            Section::make('Details')
                ->icon(Heroicon::OutlinedInformationCircle)
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('name')
                        ->label('Name'),
                    TextEntry::make('role_type.slug')
                        ->label('Role Type')
                        ->badge(),
                    TextEntry::make('users_count')
                        ->label('Assigned Users')
                        ->state(fn (Role $record): int => $record->users_count ?? $record->users()->count())
                        ->numeric(),
                ])
                ->columnSpanFull(),
            Section::make('Permissions')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->description('What people with this role can do. A personal permission covers only their own records.')
                ->schema([
                    TextEntry::make('permissions.label')
                        ->hiddenLabel()
                        ->badge()
                        ->color(fn (string $state, Role $record): string => $this->isPersonal($record, $state) ? PermissionScope::Personal->getColor() : 'primary')
                        ->formatStateUsing(fn (string $state, Role $record): string => $this->isPersonal($record, $state) ? "{$state} (Personal)" : $state)
                        ->placeholder('No permissions'),
                ])
                ->columnSpanFull(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->authorize(SystemPermission::EditRoles)
                ->hidden(fn () => $this->record->isSystemManagedType()),
        ];
    }

    /**
     * Why the role can't be changed here, when it can't.
     *
     * @return list<Section>
     */
    private function notices(): array
    {
        $notices = [];

        if ($this->record->isAssignmentLocked()) {
            $notices[] = Section::make('Assignment Locked')
                ->icon(Heroicon::OutlinedLockClosed)
                ->iconColor('warning')
                ->schema([
                    TextEntry::make('assignment_locked_warning')
                        ->hiddenLabel()
                        ->color('warning')
                        ->state('This role\'s assignment is managed programmatically and cannot be changed through the admin panel.')
                        ->columnSpanFull(),
                ])
                ->columnSpanFull();
        }

        if ($this->record->isSystemManagedType()) {
            $notices[] = Section::make('System-Managed')
                ->icon(Heroicon::OutlinedShieldExclamation)
                ->iconColor('danger')
                ->schema([
                    TextEntry::make('system_managed_warning')
                        ->hiddenLabel()
                        ->color('danger')
                        ->state('System Managed type roles are read-only and cannot be modified in the user interface.')
                        ->columnSpanFull(),
                ])
                ->columnSpanFull();
        }

        return $notices;
    }

    private function isPersonal(Role $record, string $label): bool
    {
        $permission = $record->permissions->firstWhere('label', $label);

        return $permission instanceof Permission && $permission->scope === PermissionScope::Personal;
    }
}
