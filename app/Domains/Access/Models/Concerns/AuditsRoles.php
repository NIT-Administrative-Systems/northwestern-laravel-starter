<?php

declare(strict_types=1);

namespace App\Domains\Access\Models\Concerns;

use App\Domains\Access\Enums\RoleModificationOrigin;
use App\Domains\Access\Models\Role;
use App\Domains\Core\Enums\AuditEvent;
use App\Domains\User\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as BaseCollection;
use InvalidArgumentException;

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
     * Give the user one or more roles and record a Role
     * Assigned audit: their roles before and after,
     * why they changed, and any context as tags.
     *
     * @param  Role|array<Role>|BaseCollection<int, Role>  $roles
     * @param  array<string, mixed>  $context  Added to the audit as "key: value" tags
     *
     * @throws InvalidArgumentException When a role doesn't suit the user: see {@see Role::suits()}
     */
    public function assignRoleWithAudit(
        Role|array|BaseCollection $roles,
        RoleModificationOrigin $origin,
        array $context = []
    ): void {
        $assigned = $roles instanceof BaseCollection ? $roles : collect(Arr::wrap($roles));

        if ($assigned->contains(fn (Role $role): bool => ! $role->suits($this))) {
            throw new InvalidArgumentException('An API Integration role can only be given to an API user, and other roles only to people.');
        }

        $this->loadMissing('roles.role_type');
        $oldRoles = $this->mapRolesToArray($this->roles);

        $this->assignRole($roles);

        $this->auditRoleChange(AuditEvent::RoleAssigned, $oldRoles, $origin, $context);
    }

    /**
     * Take one or more roles from the user, and record a Role Removed audit with
     * their roles before and after, why they changed, and any context as tags.
     *
     * @param  Role|array<Role>|BaseCollection<int, Role>  $roles
     * @param  array<string, mixed>  $context  Added to the audit as "key: value" tags
     */
    public function removeRoleWithAudit(
        Role|array|BaseCollection $roles,
        RoleModificationOrigin $origin,
        array $context = []
    ): void {
        $this->loadMissing('roles.role_type');
        $oldRoles = $this->mapRolesToArray($this->roles);

        $this->removeRole($roles);

        $this->auditRoleChange(AuditEvent::RoleRemoved, $oldRoles, $origin, $context);
    }

    /**
     * The origin is the audit's first tag. The roles before and after
     * share one shape, so the audit's diff compares like with like.
     *
     * @param  AuditEvent::RoleAssigned|AuditEvent::RoleRemoved  $event
     * @param  list<RoleData>  $oldRoles
     * @param  array<string, mixed>  $context
     */
    private function auditRoleChange(
        AuditEvent $event,
        array $oldRoles,
        RoleModificationOrigin $origin,
        array $context = []
    ): void {
        $freshModel = $this->fresh(['roles.role_type']);
        $newRoles = $freshModel
            ? $this->mapRolesToArray($freshModel->roles)
            : [];

        $this->recordAuditEvent($event, new: ['roles' => $newRoles], old: ['roles' => $oldRoles], tags: [$origin->value], context: $context);
    }

    /**
     * @param  BaseCollection<int, Role>  $roles
     * @return list<RoleData>
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
