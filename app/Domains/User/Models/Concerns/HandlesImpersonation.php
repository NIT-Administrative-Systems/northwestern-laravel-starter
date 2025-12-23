<?php

declare(strict_types=1);

namespace App\Domains\User\Models\Concerns;

use App\Domains\Auth\Enums\AuthTypeEnum;
use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\User\Models\User;

/**
 * Provides functionality to manage user impersonation capabilities.
 *
 * @mixin User
 */
trait HandlesImpersonation
{
    /**
     * Determine if the user is allowed to impersonate another user.
     *
     * The user must have the necessary permission to impersonate and to not be
     * impersonating another user.
     */
    public function canImpersonate(): bool
    {
        return $this->can(PermissionEnum::MANAGE_IMPERSONATION) && ! $this->isImpersonated();
    }

    /**
     * Determine if this user can impersonate a specific target user.
     */
    public function canImpersonateUser(User $target): bool
    {
        return $this->isNot($target)
            && ! app('impersonate')->isImpersonating()
            && $this->canImpersonate()
            && $target->canBeImpersonated();
    }

    /**
     * Determine if the user is allowed to be impersonated.
     *
     * The default implementation allows all users to be impersonated, but this
     * can be overridden to apply additional constraints based on business
     * rules or user-specific conditions.
     */
    public function canBeImpersonated(): bool
    {
        return $this->auth_type !== AuthTypeEnum::API;
    }

    /**
     * Check if the current session is under impersonation.
     */
    public function isImpersonated(): bool
    {
        return app('impersonate')->isImpersonating();
    }
}
