<?php

declare(strict_types=1);

namespace App\Domains\Access\Models\Concerns;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Models\Role;
use App\Domains\User\Models\User;
use Illuminate\Support\Collection;

/**
 * Which of a user's roles grant a permission,
 * for rules that depend on how someone came
 * to hold it, not only whether they do.
 *
 * @mixin User
 *
 * @phpstan-require-extends \Illuminate\Foundation\Auth\User
 */
trait TracksPermissionSources
{
    /**
     * Each role's permission names by role ID, kept for the life of this model.
     *
     * @var array<int|string, Collection<int, string>>
     */
    private array $rolePermissionsCache = [];

    /**
     * Determine whether `$role` is one of the user's roles and
     * grants them `$permission`, whatever the others grant.
     */
    public function hasPermissionFromRole(SystemPermission $permission, Role $role): bool
    {
        $this->loadMissing('roles.permissions');

        if (! $this->roles->contains('id', $role->id)) {
            return false;
        }

        return $this->getRolePermissionNames($role)->contains($permission->value);
    }

    /**
     * Get the user's roles that grant `$permission`.
     *
     * @return Collection<int, Role>
     */
    public function getRolesWithPermission(SystemPermission $permission): Collection
    {
        $this->loadMissing('roles.permissions');

        return $this->roles->filter(
            fn (Role $role): bool => $this->getRolePermissionNames($role)->contains($permission->value)
        )->values();
    }

    /**
     * Get the permissions `$role` gives the user, or none when they don't hold it.
     *
     * @return Collection<int, SystemPermission>
     */
    public function getPermissionsFromRole(Role $role): Collection
    {
        $this->loadMissing('roles.permissions');

        if (! $this->roles->contains('id', $role->id)) {
            return collect();
        }

        return $this->getRolePermissionNames($role)
            ->map(fn (string $name): ?SystemPermission => SystemPermission::tryFrom($name))
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, string>
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
