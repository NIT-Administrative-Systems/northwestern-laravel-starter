<?php

declare(strict_types=1);

namespace App\Domains\User\Actions;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Auth\Enums\AuthType;
use App\Domains\User\Enums\UserSegment;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginRecord;

/**
 * Determines a person's segment as they sign in, for the sign-in metrics.
 *
 * The sign-in record keeps it because roles change, and counting last
 * month's administrator sign-ins by today's roles would be wrong.
 *
 * Add segments as your application grows and
 * needs to count other kinds of sign-in.
 *
 * @see UserLoginRecord
 * @see UserSegment
 */
readonly class DetermineUserSegment
{
    public function __invoke(User $user): UserSegment
    {
        return match (true) {
            $this->isSuperAdmin($user) => UserSegment::SuperAdmin,
            $this->isExternalUser($user) => UserSegment::ExternalUser,
            default => UserSegment::Other,
        };
    }

    public function isSuperAdmin(User $user): bool
    {
        return $user->can(SystemPermission::ManageAll);
    }

    public function isExternalUser(User $user): bool
    {
        return $user->auth_type === AuthType::Local;
    }
}
