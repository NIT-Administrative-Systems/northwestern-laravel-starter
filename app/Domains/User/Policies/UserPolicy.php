<?php

declare(strict_types=1);

namespace App\Domains\User\Policies;

use App\Domains\User\Enums\PermissionEnum;
use App\Domains\User\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionEnum::VIEW_USERS);
    }

    public function view(User $user, User $viewedUser): bool
    {
        return $user->is($viewedUser) || $user->hasPermissionTo(PermissionEnum::VIEW_USERS);
    }

    public function edit(User $user, User $editedUser): bool
    {
        return $user->is($editedUser) || $user->hasPermissionTo(PermissionEnum::EDIT_USERS);
    }
}
