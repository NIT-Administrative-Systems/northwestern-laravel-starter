<?php

declare(strict_types=1);

namespace App\Domains\User\Policies;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Models\User;

class AuditPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ViewAuditLogs);
    }
}
