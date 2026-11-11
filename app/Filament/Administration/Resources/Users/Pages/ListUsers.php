<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Users\Pages;

use App\Filament\Administration\Resources\Roles\Actions\AddPeopleAction;
use App\Filament\Administration\Resources\Roles\Actions\ImportPeopleAction;
use App\Filament\Administration\Resources\Users\Actions\CreateApiUserAction;
use App\Filament\Administration\Resources\Users\Actions\CreateLocalUserAction;
use App\Filament\Administration\Resources\Users\Actions\CreateNorthwesternUserAction;
use App\Filament\Administration\Resources\Users\UserResource;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                CreateNorthwesternUserAction::make(),
                CreateLocalUserAction::make(),
                CreateApiUserAction::make(),
            ])
                ->label('Add User')
                ->icon(Heroicon::OutlinedUserPlus)
                ->button(),
            ActionGroup::make([
                AddPeopleAction::make(),
                ImportPeopleAction::make(),
            ])
                ->label('Add People to a Role')
                ->icon(Heroicon::OutlinedUserGroup)
                ->outlined()
                ->button(),
        ];
    }
}
