<?php

declare(strict_types=1);

namespace App\Domains\User\Policies;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ViewUsers);
    }

    public function view(User $user, User $viewedUser): bool
    {
        return $user->is($viewedUser) || $user->hasPermissionTo(SystemPermission::ViewUsers);
    }

    public function edit(User $user, User $editedUser): bool
    {
        return $user->is($editedUser) || $user->hasPermissionTo(SystemPermission::EditUsers);
    }
}
