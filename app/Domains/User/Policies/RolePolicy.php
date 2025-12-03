<?php

declare(strict_types=1);

namespace App\Domains\User\Policies;

use App\Domains\User\Enums\PermissionEnum;
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

    public function update(User $user): bool
    {
        return $user->hasPermissionTo(PermissionEnum::EDIT_ROLES);
    }
}
