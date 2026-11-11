<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Roles\Pages;

use App\Domains\Access\Actions\SyncRolePermissions;
use App\Domains\Access\Models\Role;
use App\Filament\Administration\Resources\Roles\RoleResource;
use App\Filament\Administration\Resources\Roles\Schemas\RoleForm;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;

/**
 * @property-read Role $record
 */
class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected static bool $canCreateAnother = false;

    /** @var list<string> The permissions chosen in the lists the form showed. */
    protected array $chosenPermissions = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->chosenPermissions = array_values(Arr::flatten(Arr::only($data, RoleForm::PERMISSION_LISTS)));

        return [...Arr::except($data, RoleForm::PERMISSION_LISTS), 'guard_name' => 'web'];
    }

    protected function afterCreate(): void
    {
        resolve(SyncRolePermissions::class)($this->record, $this->chosenPermissions, auth()->user());
    }

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
