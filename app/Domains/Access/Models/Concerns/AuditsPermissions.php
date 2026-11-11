<?php

declare(strict_types=1);

namespace App\Domains\Access\Models\Concerns;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Models\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\Core\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @mixin Role
 *
 * @phpstan-require-extends \Spatie\Permission\Models\Role
 *
 * @phpstan-type PermissionData array{name: string, label: string, system_managed: bool, api_relevant: bool}
 */
trait AuditsPermissions
{
    /**
     * Set the role's permissions to exactly these
     * and record a Permissions Modified audit
     * with the full list before and after.
     *
     * Nothing is recorded when nothing changed.
     *
     * @param  array<int, string|Permission|SystemPermission>  $permissions
     */
    public function syncPermissionsWithAudit(array $permissions): void
    {
        $oldPermissions = $this->mapPermissionsToArray($this->permissions);

        $this->syncPermissions($permissions);

        $freshModel = $this->fresh('permissions');
        $newPermissions = $freshModel
            ? $this->mapPermissionsToArray($freshModel->permissions)
            : [];

        $oldPermissionNames = collect($oldPermissions)->pluck('name')->toArray();
        $newPermissionNames = collect($newPermissions)->pluck('name')->toArray();

        $addedPermissionNames = array_diff($newPermissionNames, $oldPermissionNames);
        $removedPermissionNames = array_diff($oldPermissionNames, $newPermissionNames);

        if (filled($addedPermissionNames) || filled($removedPermissionNames)) {
            $this->auditPermissionChange($oldPermissions, $newPermissions);
        }
    }

    /**
     * @param  EloquentCollection<int, SpatiePermission>  $permissions
     * @return list<PermissionData>
     */
    private function mapPermissionsToArray(EloquentCollection $permissions): array
    {
        /** @var EloquentCollection<int, Permission> $permissions */
        return array_values($permissions->map(fn (Permission $permission): array => [
            'name' => $permission->name,
            'label' => $permission->label,
            'system_managed' => $permission->system_managed,
            'api_relevant' => $permission->api_relevant,
        ])->all());
    }

    /**
     * @param  list<PermissionData>  $oldPermissions
     * @param  list<PermissionData>  $newPermissions
     */
    private function auditPermissionChange(array $oldPermissions, array $newPermissions): void
    {
        $this->recordAuditEvent(AuditEvent::PermissionsModified, new: ['permissions' => $newPermissions], old: ['permissions' => $oldPermissions]);
    }
}
