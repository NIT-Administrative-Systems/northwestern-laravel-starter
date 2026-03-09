<?php

declare(strict_types=1);

namespace App\Domains\Auth\Policies;

use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionEnum::VIEW_ROLES);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionEnum::EDIT_ROLES);
    }

    public function update(User $user, Role $role): bool
    {
        if ($role->isSystemManagedType()) {
            return false;
        }

        return $user->hasPermissionTo(PermissionEnum::EDIT_ROLES);
    }

    public function delete(User $user, Role $role): bool
    {
        if ($role->isSystemManagedType()) {
            return false;
        }

        return $user->hasPermissionTo(PermissionEnum::DELETE_ROLES);
    }

    /**
     * Permission check for assigning this role to users.
     *
     * System Managed roles require MANAGE_ALL permission.
     * Does NOT check assignment_locked — that's enforced outside the gate
     * so it cannot be bypassed by super admins.
     */
    public function attachUser(User $user, Role $role): bool
    {
        if ($role->isSystemManagedType()) {
            return $user->hasPermissionTo(PermissionEnum::MANAGE_ALL);
        }

        return $user->hasPermissionTo(PermissionEnum::ASSIGN_ROLES);
    }

    /**
     * Permission check for removing this role from users.
     */
    public function detachUser(User $user, Role $role): bool
    {
        return $this->attachUser($user, $role);
    }
}
