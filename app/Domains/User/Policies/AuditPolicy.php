<?php

declare(strict_types=1);

namespace App\Domains\User\Policies;

use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\User\Models\User;

class AuditPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionEnum::VIEW_AUDIT_LOGS);
    }
}
