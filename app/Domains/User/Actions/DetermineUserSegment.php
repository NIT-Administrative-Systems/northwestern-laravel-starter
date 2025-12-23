<?php

declare(strict_types=1);

namespace App\Domains\User\Actions;

use App\Domains\Auth\Enums\AuthTypeEnum;
use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\User\Enums\UserSegmentEnum;
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
 * @see UserSegmentEnum
 */
readonly class DetermineUserSegment
{
    public function __invoke(User $user): UserSegmentEnum
    {
        return match (true) {
            $this->isSuperAdmin($user) => UserSegmentEnum::SUPER_ADMIN,
            $this->isExternalUser($user) => UserSegmentEnum::EXTERNAL_USER,
            default => UserSegmentEnum::OTHER,
        };
    }

    public function isSuperAdmin(User $user): bool
    {
        return $user->can(PermissionEnum::MANAGE_ALL);
    }

    public function isExternalUser(User $user): bool
    {
        return $user->auth_type === AuthTypeEnum::LOCAL;
    }
}
