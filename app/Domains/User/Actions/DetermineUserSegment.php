<?php

declare(strict_types=1);

namespace App\Domains\User\Actions;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Enums\UserSegment;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginRecord;

/**
 * Determines the user segment at login time for historical metrics.
 *
 * This action classifies users into segments when they log in, storing the segment
 * in the UserLoginRecord. This is crucial because user roles and permissions can
 * change over time, but we want to know what segment they were in at each login.
 *
 * **Why This Matters:**
 * - User roles can change (promoted to admin, demoted, etc.)
 * - Historical login metrics should reflect the user's segment at that moment
 * - Enables accurate reporting on "how many admin logins this month?"
 *
 * Expand this logic as your application grows to capture more relevant metrics.
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
