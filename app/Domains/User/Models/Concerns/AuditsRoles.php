<?php

declare(strict_types=1);

namespace App\Domains\User\Models\Concerns;

use App\Domains\User\Models\Role;
use App\Domains\User\Models\User;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * @mixin User
 */
trait AuditsRoles
{
    /**
     * Assigns a role to the user and creates a detailed audit log entry.
     *
     * This method captures the complete state of the user's roles both before and after
     * the assignment, creating a comprehensive audit trail. The audit log includes:
     * - All roles the user had before the change
     * - The specific role that was assigned
     * - All roles the user has after the change
     *
     * @param  Role  $role  The role to assign to the user
     *
     * @see auditRoleChange() for the audit event structure
     * @see removeRoleWithAudit() for the inverse operation
     */
    public function assignRoleWithAudit(Role $role): void
    {
        $this->loadMissing('roles.role_type');
        $oldRoles = $this->mapRolesToArray($this->roles);
        $this->assignRole($role);
        $this->auditRoleChange('role_assigned', $oldRoles, $role);
    }

    /**
     * Removes a role from the user and creates a detailed audit log entry.
     *
     * This method captures the complete state of the user's roles both before and after
     * the removal, creating a comprehensive audit trail. The audit log includes:
     * - All roles the user had before the change
     * - The specific role that was removed
     * - All roles the user has after the change
     *
     * @param  Role  $role  The role to remove from the user
     *
     * @see auditRoleChange() for the audit event structure
     * @see assignRoleWithAudit() for the inverse operation
     */
    public function removeRoleWithAudit(Role $role): void
    {
        $this->loadMissing('roles.role_type');
        $oldRoles = $this->mapRolesToArray($this->roles);
        $this->removeRole($role);
        $this->auditRoleChange('role_removed', $oldRoles, $role);
    }

    /**
     * Converts a collection of roles to a simplified array format.
     *
     * @param  BaseCollection<int, Role>  $roles
     * @return array<int, array{id: int, name: string, role_type: string}> Array of simplified role data
     */
    private function mapRolesToArray(BaseCollection $roles): array
    {
        return $roles->map(fn (Role $role): array => [
            'id' => (int) $role->id,
            'name' => $role->name,
            'role_type' => $role->role_type->slug->getLabel(),
        ])->toArray();
    }

    /**
     * Creates a custom audit log entry for role changes with before/after snapshots.
     *
     * This method constructs a specialized audit event that captures the complete context
     * of a role assignment or removal. Unlike standard model audits that only track
     * attribute changes, this creates a structured snapshot of the entire role collection.
     *
     * @param  'role_assigned'|'role_removed'  $event  The specific audit event type
     * @param  array<int, array{id: int, name: string, role_type: string}>  $oldRoles  The collection of roles before modification
     * @param  Role  $role  The role that was assigned or removed
     *
     * @see assignRoleWithAudit()
     * @see removeRoleWithAudit()
     */
    private function auditRoleChange(string $event, array $oldRoles, Role $role): void
    {
        // Get latest roles after the modification
        $newRoles = $this->mapRolesToArray(
            $this->fresh(['roles.role_type'])->roles
        );

        $isAssignment = $event === 'role_assigned';

        $auditData = [
            'auditEvent' => $event,
            'isCustomEvent' => true,
            'auditCustomOld' => [
                'roles_before_change' => $oldRoles,
            ],
            'auditCustomNew' => [
                $isAssignment ? 'assigned_role' :
                    'removed_role' => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'role_type' => $role->role_type->slug->getLabel(),
                    ],
                'roles_after_change' => $newRoles,
            ],
        ];

        foreach ($auditData as $key => $value) {
            $this->{$key} = $value;
        }

        Event::dispatch(new AuditCustom($this));
    }
}
