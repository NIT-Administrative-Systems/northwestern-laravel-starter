<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Domains\Auth\Enums\RoleModificationOrigin;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;
use App\Filament\Resources\Roles\RoleResource;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * @property-read Role $record
 */
class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected static ?string $navigationLabel = 'Details';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInformationCircle;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_if($this->record->isSystemManagedType(), 403, 'System Managed roles cannot be edited.');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->authorize(fn () => ! $this->record->isSystemManagedType() && Gate::allows('delete', $this->record))
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
                            $user->removeRoleWithAudit($record, RoleModificationOrigin::RemovedByDeletion);
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

        if (auth()->user()->hasPermissionTo(SystemPermission::ManageAll)) {
            $systemPermissions = $this->data['system_permissions'] ?? [];
        } else {
            // Preserve existing system permissions the user cannot manage
            $systemPermissions = $this->record->permissions
                ->filter(fn ($p) => SystemPermission::tryFrom($p->name)?->isSystemManaged())
                ->pluck('name')
                ->toArray();
        }

        $allPermissions = array_merge($apiPermissions, $regularPermissions, $systemPermissions);

        $this->record->syncPermissionsWithAudit($allPermissions);
    }
}
