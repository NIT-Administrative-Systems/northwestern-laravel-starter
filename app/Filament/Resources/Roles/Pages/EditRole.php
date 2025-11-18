<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Domains\User\Enums\PermissionEnum;
use App\Domains\User\Models\Role;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * @property-read Role $record
 */
class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_if($this->record->isSystemManagedType(), 403, 'System Managed roles cannot be edited.');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->authorize(PermissionEnum::DELETE_ROLES)
                ->hidden(fn () => $this->record->isSystemManagedType())
                ->before(function () {
                    // Remove role from all assigned users before deleting
                    $this->record->users()->each(function ($user) {
                        $user->removeRole($this->record);
                    });
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['guard_name'] = 'web';

        return $data;
    }

    protected function afterSave(): void
    {
        $apiPermissions = $this->data['api_permissions'] ?? [];
        $regularPermissions = $this->data['regular_permissions'] ?? [];
        $systemPermissions = $this->data['system_permissions'] ?? [];
        $allPermissions = array_merge($apiPermissions, $regularPermissions, $systemPermissions);

        $this->record->syncPermissionsWithAudit($allPermissions);
    }
}
