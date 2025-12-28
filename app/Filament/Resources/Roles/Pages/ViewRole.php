<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Domains\Auth\Enums\PermissionEnum;
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

        // Add warning section for System Managed roles at the top
        if ($this->record->isSystemManagedType()) {
            array_unshift($components, Section::make('System Managed Role')
                ->icon(Heroicon::OutlinedShieldExclamation)
                ->iconColor('danger')
                ->schema([
                    TextEntry::make('warning')
                        ->hiddenLabel()
                        ->color('danger')
                        ->state('System Managed type roles are read-only and cannot be modified in the user interface.')
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
                ->authorize(PermissionEnum::EDIT_ROLES)
                ->hidden(fn () => $this->record->isSystemManagedType()),
        ];
    }
}
