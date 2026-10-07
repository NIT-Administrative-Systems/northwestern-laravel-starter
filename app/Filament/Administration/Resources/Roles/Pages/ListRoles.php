<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Roles\Pages;

use App\Domains\Auth\Enums\SystemPermission;
use App\Filament\Administration\Resources\RoleActivity\RoleActivityResource;
use App\Filament\Administration\Resources\Roles\RoleResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('role_activity')
                ->label('Role Activity')
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->outlined()
                ->url(RoleActivityResource::getUrl('index'))
                ->authorize(SystemPermission::ViewAuditLogs),

            CreateAction::make()
                ->authorize(SystemPermission::EditRoles)
                ->label('Create Role')
                ->icon(Heroicon::OutlinedPlusCircle),
        ];
    }
}
