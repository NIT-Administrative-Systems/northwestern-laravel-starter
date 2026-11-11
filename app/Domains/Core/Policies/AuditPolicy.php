<?php

declare(strict_types=1);

namespace App\Domains\Core\Policies;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Core\Models\Audit;
use App\Domains\User\Models\User;

/**
 * Who may read the audit log. Laravel finds a policy by its model's
 * namespace, so this lives in the domain that owns {@see Audit}.
 */
class AuditPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ViewAuditLogs);
    }

    public function view(User $user, Audit $audit): bool
    {
        return $this->viewAny($user);
    }
}
