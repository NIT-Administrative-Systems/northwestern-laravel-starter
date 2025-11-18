<?php

declare(strict_types=1);

namespace App\Domains\User\Policies;

use App\Domains\User\Enums\PermissionEnum;
use App\Domains\User\Models\User;

class UserLoginRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionEnum::VIEW_LOGIN_RECORDS);
    }
}
