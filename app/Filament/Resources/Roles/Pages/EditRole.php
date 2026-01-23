<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\Auth\Enums\RoleModificationOriginEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

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
                ->modalDescription(function (Role $record): HtmlString {
                    $userCount = $record->users()->count();

                    if ($userCount === 0) {
                        return new HtmlString('Are you sure you want to delete this role? This action cannot be undone.');
                    }

                    $userText = $userCount . ' ' . Str::plural('user', $userCount);

                    return new HtmlString(
                        "<span class=\"text-danger-600 dark:text-danger-400 font-medium\">This role is assigned to {$userText}.</span><br><br>" .
                        'Deleting it will revoke their permissions granted by this role. This action cannot be undone.'
                    );
                })
                ->before(function (Role $record) {
                    // Remove the role from all assigned users before deleting
                    $record->users()
                        ->lazyById(100)
                        ->each(function (User $user) use ($record) {
                            $user->removeRoleWithAudit($record, RoleModificationOriginEnum::REMOVED_BY_DELETION);
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
