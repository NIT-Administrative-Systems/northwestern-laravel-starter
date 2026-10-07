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
     * @param  array<int, string|Permission|SystemPermission>  $permissions  The complete set of permissions this role should have
     *
     * @see auditPermissionChange() for the audit event structure
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
     * Converts a collection of permissions to a simplified array format.
     *
     * @param  EloquentCollection<int, SpatiePermission>  $permissions
     * @return list<PermissionData> Array of simplified permission data
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
     * Creates a custom audit log entry for permission changes with before/after snapshots.
     *
     * This method constructs a specialized audit event that captures the complete context
     * of a permission sync operation. Unlike standard model audits that only track column
     * changes, this creates a structured snapshot of the permission collection.
     *
     * @param  list<PermissionData>  $oldPermissions  Permissions before modification
     * @param  list<PermissionData>  $newPermissions  Permissions after modification
     *
     * @see syncPermissionsWithAudit()
     */
    private function auditPermissionChange(array $oldPermissions, array $newPermissions): void
    {
        $this->recordAuditEvent(AuditEvent::PermissionsModified, new: ['permissions' => $newPermissions], old: ['permissions' => $oldPermissions]);
    }
}
