<?php

declare(strict_types=1);

namespace App\Domains\User\Models\Concerns;

use App\Domains\User\Models\Permission;
use App\Domains\User\Models\Role;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * @mixin Role
 */
trait AuditsPermissions
{
    /**
     * Synchronizes permissions for this role and creates a detailed audit log entry.
     *
     * This method performs a "sync" operation: it sets the role's permissions to exactly
     * match the provided array, adding missing permissions and removing extras. It then
     * creates a comprehensive audit trail that captures:
     * - All permissions before the sync
     * - Which permissions were added (if any)
     * - Which permissions were removed (if any)
     * - All permissions after the sync
     *
     * @param  array<int, string|Permission>  $permissions  The complete set of permissions this role should have
     *
     * @see auditPermissionChange() for the audit event structure
     */
    public function syncPermissionsWithAudit(array $permissions): void
    {
        $oldPermissions = $this->mapPermissionsToArray($this->permissions);

        $this->syncPermissions($permissions);

        $newPermissions = $this->mapPermissionsToArray(
            $this->fresh('permissions')->permissions
        );

        $oldPermissionNames = collect($oldPermissions)->pluck('name')->toArray();
        $newPermissionNames = collect($newPermissions)->pluck('name')->toArray();

        $addedPermissionNames = array_diff($newPermissionNames, $oldPermissionNames);
        $removedPermissionNames = array_diff($oldPermissionNames, $newPermissionNames);

        // Only create audit if there were changes
        if (filled($addedPermissionNames) || filled($removedPermissionNames)) {
            $addedPermissions = collect($newPermissions)
                ->whereIn('name', $addedPermissionNames)
                ->values()
                ->toArray();

            $removedPermissions = collect($oldPermissions)
                ->whereIn('name', $removedPermissionNames)
                ->values()
                ->toArray();

            $this->auditPermissionChange($oldPermissions, $newPermissions, $addedPermissions, $removedPermissions);
        }
    }

    /**
     * Converts a collection of permissions to a simplified array format.
     *
     * @return array<int, array{name: string, label: string, system_managed: bool}> Array of simplified permission data
     */
    private function mapPermissionsToArray(BaseCollection $permissions): array
    {
        return $permissions->map(fn (Permission $permission): array => [
            'name' => $permission->name,
            'label' => $permission->label,
            'system_managed' => $permission->system_managed,
            'api_relevant' => $permission->api_relevant,
        ])->toArray();
    }

    /**
     * Creates a custom audit log entry for permission changes with detailed diff information.
     *
     * This method constructs a specialized audit event that captures the complete context
     * of a permission sync operation. Unlike standard model audits that only track column
     * changes, this creates a structured snapshot of the permission collection with a diff.
     *
     * @param  array<int, array{name: string, label: string, system_managed: bool}>  $oldPermissions  Permissions before modification
     * @param  array<int, array{name: string, label: string, system_managed: bool}>  $newPermissions  Permissions after modification
     * @param  array<int, array{name: string, label: string, system_managed: bool}>  $addedPermissions  The permissions that were added
     * @param  array<int, array{name: string, label: string, system_managed: bool}>  $removedPermissions  The permissions that were removed
     *
     * @see syncPermissionsWithAudit()
     */
    private function auditPermissionChange(array $oldPermissions, array $newPermissions, array $addedPermissions, array $removedPermissions): void
    {
        $auditData = [
            'auditEvent' => 'permissions_modified',
            'isCustomEvent' => true,
            'auditCustomOld' => [
                'permissions_before_change' => $oldPermissions,
            ],
            'auditCustomNew' => array_filter([
                'added_permissions' => filled($addedPermissions) ? $addedPermissions : null,
                'removed_permissions' => filled($removedPermissions) ? $removedPermissions : null,
                'permissions_after_change' => $newPermissions,
            ]),
        ];

        foreach ($auditData as $key => $value) {
            $this->{$key} = $value;
        }

        Event::dispatch(new AuditCustom($this));
    }
}
