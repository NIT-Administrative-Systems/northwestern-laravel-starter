<?php

declare(strict_types=1);

namespace App\Domains\Access\Policies;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Models\Role;
use App\Domains\User\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ViewRoles);
    }

    public function view(User $user, Role $role): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::EditRoles);
    }

    public function update(User $user, Role $role): bool
    {
        if ($role->isSystemManagedType()) {
            return false;
        }

        return $user->hasPermissionTo(SystemPermission::EditRoles);
    }

    public function delete(User $user, Role $role): bool
    {
        if ($role->isSystemManagedType()) {
            return false;
        }

        return $user->hasPermissionTo(SystemPermission::DeleteRoles);
    }

    /**
     * Determine whether the user may give this role to
     * people, or to `$assignee` when one is named.
     *
     * Nobody may give themselves a role. System-managed roles are refused
     * here, so only Manage All, through Gate::before(), can assign one.
     *
     * Assignment locks and self-assignment are refused outside
     * the gate as well, where Manage All can't bypass them.
     */
    public function attachUser(User $user, Role $role, ?User $assignee = null): bool
    {
        if ($role->isSystemManagedType() || $user->is($assignee)) {
            return false;
        }

        return $user->hasPermissionTo(SystemPermission::AssignRoles);
    }

    /**
     * Determine whether the user may take this role from people, themselves included.
     */
    public function detachUser(User $user, Role $role): bool
    {
        return $this->attachUser($user, $role);
    }
}
