<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Actions\CreateApiUserAction;
use App\Filament\Resources\Users\Actions\CreateLocalUserAction;
use App\Filament\Resources\Users\Actions\CreateNorthwesternUserAction;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;

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
                ->label('Actions')
                ->button(),
        ];
    }
}
