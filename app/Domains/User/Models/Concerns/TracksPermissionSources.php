<?php

declare(strict_types=1);

namespace App\Domains\User\Models\Concerns;

use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;
use Illuminate\Support\Collection;

/**
 * Provides methods to check if a user has a permission through a specific role.
 *
 * This trait enables fine-grained permission source checking, which is useful when:
 * - Implementing hierarchical permissions where the same permission may come from different roles
 * - Applying different behaviors based on which role grants access (e.g., different rate limits for API vs admin roles)
 * - Building authorization logic that depends on the context of how access was granted
 *
 * @mixin User
 *
 * @phpstan-require-extends \Illuminate\Foundation\Auth\User
 */
trait TracksPermissionSources
{
    /**
     * Memoization cache for role permission lookups.
     *
     * @var array<int|string, Collection<int, string>>
     */
    private array $rolePermissionsCache = [];

    /**
     * Determine if the user has a specific permission granted through a specific role.
     *
     * This method checks whether a permission is granted to the user specifically
     * through the given role, not just whether the user has the permission at all.
     * This distinction matters when authorization logic depends on *how* the user
     * obtained a permission.
     *
     * @param  PermissionEnum  $permission  The permission to check
     * @param  Role  $role  The role that should grant this permission
     * @return bool True if the user has this role AND the role includes this permission
     */
    public function hasPermissionFromRole(PermissionEnum $permission, Role $role): bool
    {
        $this->loadMissing('roles.permissions');

        if (! $this->roles->contains('id', $role->id)) {
            return false;
        }

        return $this->getRolePermissionNames($role)->contains($permission->value);
    }

    /**
     * Get all roles that grant the user a specific permission.
     *
     * @param  PermissionEnum  $permission  The permission to check
     * @return Collection<int, Role> Collection of roles that grant this permission
     */
    public function getRolesWithPermission(PermissionEnum $permission): Collection
    {
        $this->loadMissing('roles.permissions');

        return $this->roles->filter(
            fn (Role $role): bool => $this->getRolePermissionNames($role)->contains($permission->value)
        )->values();
    }

    /**
     * Get all permissions the user has through a specific role.
     *
     * Returns only the permissions that both:
     * 1. The user has (through any role)
     * 2. Are granted by the specified role
     *
     * @param  Role  $role  The role to get permissions from
     * @return Collection<int, PermissionEnum> Collection of permissions from this role
     */
    public function getPermissionsFromRole(Role $role): Collection
    {
        $this->loadMissing('roles.permissions');

        if (! $this->roles->contains('id', $role->id)) {
            return collect();
        }

        return $this->getRolePermissionNames($role)
            ->map(fn (string $name): ?PermissionEnum => PermissionEnum::tryFrom($name))
            ->filter()
            ->values();
    }

    /**
     * Get all permission names granted by a specific role.
     *
     * Results are memoized per-role for the lifetime of this model instance
     * to avoid redundant database queries when checking multiple permissions
     * against the same role.
     *
     * @param  Role  $role  The role to get permissions for
     * @return Collection<int, string> Collection of permission names (string values)
     */
    private function getRolePermissionNames(Role $role): Collection
    {
        if (! isset($this->rolePermissionsCache[$role->id])) {
            $role->loadMissing('permissions');
            $this->rolePermissionsCache[$role->id] = $role->permissions->pluck('name');
        }

        return $this->rolePermissionsCache[$role->id];
    }
}
