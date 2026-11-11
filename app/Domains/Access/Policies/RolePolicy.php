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
     * Permission check for assigning this role to users, or to `$assignee` when one is named:
     * nobody may give themselves a role.
     *
     * System Managed roles return false here — super admins gain access
     * via Gate::before(), consistent with how all other policies work.
     * Does NOT check assignment_locked — that's enforced outside the gate
     * so it cannot be bypassed by super admins. The relation managers refuse
     * self-assignment outside the gate too, for the same reason.
     */
    public function attachUser(User $user, Role $role, ?User $assignee = null): bool
    {
        if ($role->isSystemManagedType() || $user->is($assignee)) {
            return false;
        }

        return $user->hasPermissionTo(SystemPermission::AssignRoles);
    }

    /**
     * Permission check for removing this role from users. People may remove their own roles.
     */
    public function detachUser(User $user, Role $role): bool
    {
        return $this->attachUser($user, $role);
    }
}
