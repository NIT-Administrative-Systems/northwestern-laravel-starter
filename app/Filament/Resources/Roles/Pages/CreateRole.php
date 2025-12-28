<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Domains\Auth\Models\Role;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * @property-read Role $record
 */
class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['guard_name'] = 'web';

        return $data;
    }

    protected function afterCreate(): void
    {
        $apiPermissions = $this->data['api_permissions'] ?? [];
        $regularPermissions = $this->data['regular_permissions'] ?? [];
        $systemPermissions = $this->data['system_permissions'] ?? [];
        $allPermissions = array_merge($apiPermissions, $regularPermissions, $systemPermissions);

        $this->record->syncPermissionsWithAudit($allPermissions);
    }

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
