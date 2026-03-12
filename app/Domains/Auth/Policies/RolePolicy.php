<?php

declare(strict_types=1);

namespace App\Domains\Auth\Policies;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ViewRoles);
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
     * Permission check for assigning this role to users.
     *
     * System Managed roles return false here — super admins gain access
     * via Gate::before(), consistent with how all other policies work.
     * Does NOT check assignment_locked — that's enforced outside the gate
     * so it cannot be bypassed by super admins.
     */
    public function attachUser(User $user, Role $role): bool
    {
        if ($role->isSystemManagedType()) {
            return false;
        }

        return $user->hasPermissionTo(SystemPermission::AssignRoles);
    }

    /**
     * Permission check for removing this role from users.
     */
    public function detachUser(User $user, Role $role): bool
    {
        return $this->attachUser($user, $role);
    }
}
