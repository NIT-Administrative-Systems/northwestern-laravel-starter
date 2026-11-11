<?php

declare(strict_types=1);

namespace App\Domains\Api\Policies;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Api\Models\ApiRequestLog;
use App\Domains\User\Models\User;

/**
 * Allows reading the log of API requests, in Administration.
 * The log is read-only, so there is nothing else to allow.
 */
class ApiRequestLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(SystemPermission::ViewApiRequestLogs);
    }

    public function view(User $user, ApiRequestLog $log): bool
    {
        return $this->viewAny($user);
    }
}
