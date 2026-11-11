<?php

declare(strict_types=1);

namespace App\Domains\User\Policies;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginRecord;

class UserLoginRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ViewLoginRecords);
    }

    public function view(User $user, UserLoginRecord $record): bool
    {
        return $this->viewAny($user);
    }
}
