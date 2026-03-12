<?php

declare(strict_types=1);

namespace App\Domains\User\Models\Concerns;

use App\Domains\Auth\Enums\RoleModificationOrigin;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * @mixin User
 *
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 *
 * @phpstan-type RoleData array{id: int, name: string, role_type: string}
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
     * @param  RoleModificationOrigin  $origin  The source/reason for this role change
     * @param  array<string, mixed>  $context  Additional contextual information
     *
     * @see auditRoleChange() for the audit event structure
     * @see removeRoleWithAudit() for the inverse operation
     */
    public function assignRoleWithAudit(
        Role|array|BaseCollection $roles,
        RoleModificationOrigin $origin,
        array $context = []
    ): void {
        $this->loadMissing('roles.role_type');
        $oldRoles = $this->mapRolesToArray($this->roles);

        $this->assignRole($roles);

        $this->auditRoleChange('role_assigned', $oldRoles, $origin, $context);
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
     * @param  RoleModificationOrigin  $origin  The source/reason for this role change
     * @param  array<string, mixed>  $context  Additional contextual information
     *
     * @see auditRoleChange() for the audit event structure
     * @see assignRoleWithAudit() for the inverse operation
     */
    public function removeRoleWithAudit(
        Role|array|BaseCollection $roles,
        RoleModificationOrigin $origin,
        array $context = []
    ): void {
        $this->loadMissing('roles.role_type');
        $oldRoles = $this->mapRolesToArray($this->roles);

        $this->removeRole($roles);

        $this->auditRoleChange('role_removed', $oldRoles, $origin, $context);
    }

    /**
     * Creates a custom audit log entry for role changes with before/after snapshots.
     *
     * The modification origin is stored as a tag, and optional context entries are
     * appended as tags (e.g., "reason: promoted"). The diff between old and new
     * values uses an identical structure for clean visual comparison.
     *
     * @param  'role_assigned'|'role_removed'  $event  The specific audit event type
     * @param  list<RoleData>  $oldRoles  The collection of roles before modification
     * @param  RoleModificationOrigin  $origin  The source/reason for this role change
     * @param  array<string, mixed>  $context  Additional contextual information
     *
     * @see assignRoleWithAudit()
     * @see removeRoleWithAudit()
     */
    private function auditRoleChange(
        string $event,
        array $oldRoles,
        RoleModificationOrigin $origin,
        array $context = []
    ): void {
        // Get latest roles after the modification
        $freshModel = $this->fresh(['roles.role_type']);
        $newRoles = $freshModel
            ? $this->mapRolesToArray($freshModel->roles)
            : [];

        $auditData = [
            'auditEvent' => $event,
            'isCustomEvent' => true,
            'auditCustomOld' => [
                'roles' => $oldRoles,
            ],
            'auditCustomNew' => [
                'roles' => $newRoles,
            ],
        ];

        foreach ($auditData as $key => $value) {
            $this->{$key} = $value;
        }

        $this->auditCustomTags = [$origin->value];
        $this->auditCustomContext = filled($context) ? $context : null;

        Event::dispatch(new AuditCustom($this));

        unset($this->auditCustomTags, $this->auditCustomContext);
    }

    /**
     * Converts a collection of roles to a simplified array format.
     *
     * @param  BaseCollection<int, Role>  $roles
     * @return list<RoleData> Array of simplified role data
     */
    private function mapRolesToArray(BaseCollection $roles): array
    {
        return array_values($roles->map(fn (Role $role): array => [
            'id' => (int) $role->id,
            'name' => $role->name,
            'role_type' => $role->role_type?->slug->getLabel() ?? 'Unknown',
        ])->all());
    }
}
