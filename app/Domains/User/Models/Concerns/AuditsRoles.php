<?php

declare(strict_types=1);

namespace App\Domains\User\Models\Concerns;

use App\Domains\User\Enums\RoleModificationOriginEnum;
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
     * Assigns one or more roles to the user and creates a detailed audit log entry.
     *
     * This method captures the complete state of the user's roles both before and after
     * the assignment, creating a comprehensive audit trail. The audit log includes:
     * - All roles the user had before the change
     * - The specific role(s) that were assigned
     * - All roles the user has after the change
     * - The origin/reason for the change
     * - Optional contextual metadata
     *
     * @param  Role|array<Role>|BaseCollection<int, Role>  $roles  The role(s) to assign
     * @param  RoleModificationOriginEnum  $origin  The source/reason for this role change
     * @param  array<string, mixed>  $context  Additional contextual information
     *
     * @see auditRoleChange() for the audit event structure
     * @see removeRoleWithAudit() for the inverse operation
     */
    public function assignRoleWithAudit(
        Role|array|BaseCollection $roles,
        RoleModificationOriginEnum $origin,
        array $context = []
    ): void {
        $this->loadMissing('roles.role_type');
        $oldRoles = $this->mapRolesToArray($this->roles);

        $this->assignRole($roles);

        $normalizedRoles = $this->normalizeRolesToCollection($roles);
        $this->auditRoleChange('role_assigned', $oldRoles, $normalizedRoles, $origin, $context);
    }

    /**
     * Removes one or more roles from the user and creates a detailed audit log entry.
     *
     * This method captures the complete state of the user's roles both before and after
     * the removal, creating a comprehensive audit trail. The audit log includes:
     * - All roles the user had before the change
     * - The specific role(s) that were removed
     * - All roles the user has after the change
     * - The origin/reason for the change
     * - Optional contextual metadata
     *
     * @param  Role|array<Role>|BaseCollection<int, Role>  $roles  The role(s) to remove
     * @param  RoleModificationOriginEnum  $origin  The source/reason for this role change
     * @param  array<string, mixed>  $context  Additional contextual information
     *
     * @see auditRoleChange() for the audit event structure
     * @see assignRoleWithAudit() for the inverse operation
     */
    public function removeRoleWithAudit(
        Role|array|BaseCollection $roles,
        RoleModificationOriginEnum $origin,
        array $context = []
    ): void {
        $this->loadMissing('roles.role_type');
        $oldRoles = $this->mapRolesToArray($this->roles);

        $this->removeRole($roles);

        $normalizedRoles = $this->normalizeRolesToCollection($roles);
        $this->auditRoleChange('role_removed', $oldRoles, $normalizedRoles, $origin, $context);
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
     * @param  BaseCollection<int, Role>  $roles  The role(s) that were assigned or removed
     * @param  RoleModificationOriginEnum  $origin  The source/reason for this role change
     * @param  array<string, mixed>  $context  Additional contextual information
     *
     * @see assignRoleWithAudit()
     * @see removeRoleWithAudit()
     */
    private function auditRoleChange(
        string $event,
        array $oldRoles,
        BaseCollection $roles,
        RoleModificationOriginEnum $origin,
        array $context = []
    ): void {
        // Get latest roles after the modification
        $newRoles = $this->mapRolesToArray(
            $this->fresh(['roles.role_type'])->roles
        );

        $isAssignment = $event === 'role_assigned';
        $modifiedRoles = $this->mapRolesToArray($roles);

        $auditNew = [
            $isAssignment ? 'assigned_roles' : 'removed_roles' => $modifiedRoles,
            'roles_after_change' => $newRoles,
            'modification_origin' => $origin->value,
        ];

        if (filled($context)) {
            $auditNew['context'] = $context;
        }

        $auditData = [
            'auditEvent' => $event,
            'isCustomEvent' => true,
            'auditCustomOld' => [
                'roles_before_change' => $oldRoles,
            ],
            'auditCustomNew' => $auditNew,
        ];

        foreach ($auditData as $key => $value) {
            $this->{$key} = $value;
        }

        Event::dispatch(new AuditCustom($this));
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
     * Normalizes various role input formats into a collection of Role models.
     *
     * @param  Role|array<Role>|BaseCollection<int, Role>  $roles
     * @return BaseCollection<int, Role>
     */
    private function normalizeRolesToCollection(Role|array|BaseCollection $roles): BaseCollection
    {
        if ($roles instanceof Role) {
            return collect([$roles]);
        }

        if ($roles instanceof BaseCollection) {
            return $roles;
        }

        return collect($roles);
    }
}
