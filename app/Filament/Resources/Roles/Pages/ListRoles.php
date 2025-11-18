<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Domains\User\Enums\PermissionEnum;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->authorize(PermissionEnum::MODIFY_ROLES)
                ->label('Create Role')
                ->icon(Heroicon::OutlinedPlusCircle),
        ];
    }
}
