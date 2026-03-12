<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\Role;
use App\Filament\Resources\Roles\RoleResource;
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

    public function form(Schema $schema): Schema
    {
        $schema = parent::form($schema);
        $components = $schema->getComponents();

        if ($this->record->isSystemManagedType()) {
            array_unshift($components, Section::make('System Managed')
                ->icon(Heroicon::OutlinedShieldExclamation)
                ->iconColor('danger')
                ->schema([
                    TextEntry::make('system_managed_warning')
                        ->hiddenLabel()
                        ->color('danger')
                        ->state('System Managed type roles are read-only and cannot be modified in the user interface.')
                        ->columnSpanFull(),
                ])
                ->columnSpanFull());
        }

        if ($this->record->isAssignmentLocked()) {
            array_unshift($components, Section::make('Assignment Locked')
                ->icon(Heroicon::OutlinedLockClosed)
                ->iconColor('warning')
                ->schema([
                    TextEntry::make('assignment_locked_warning')
                        ->hiddenLabel()
                        ->color('warning')
                        ->state('This role\'s assignment is managed programmatically and cannot be changed through the admin panel.')
                        ->columnSpanFull(),
                ])
                ->columnSpanFull());
        }

        return $schema->components($components);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->authorize(SystemPermission::EditRoles)
                ->hidden(fn () => $this->record->isSystemManagedType()),
        ];
    }
}
