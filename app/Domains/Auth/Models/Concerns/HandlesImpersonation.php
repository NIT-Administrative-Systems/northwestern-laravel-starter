<?php

declare(strict_types=1);

namespace App\Domains\Auth\Models\Concerns;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Auth\Enums\AuthType;
use App\Domains\User\Models\User;

/**
 * Who may impersonate whom, as lab404's impersonation package asks.
 *
 * @mixin User
 */
trait HandlesImpersonation
{
    /**
     * Determine if the user is allowed to impersonate another user.
     *
     * They need the impersonation permission and can't already be impersonating.
     */
    public function canImpersonate(): bool
    {
        return $this->can(SystemPermission::ManageImpersonation) && ! $this->isImpersonated();
    }

    /**
     * Determine if this user can impersonate a specific target user.
     */
    public function canImpersonateUser(User $target): bool
    {
        return $this->isNot($target)
            && $this->canImpersonate()
            && $target->canBeImpersonated();
    }

    /**
     * Determine if the user is allowed to be impersonated.
     *
     * Override this to add rules of your own about who may be impersonated.
     */
    public function canBeImpersonated(): bool
    {
        // API users have no session, and deactivated
        // or deleted people can't use the panels.
        if ($this->auth_type === AuthType::API || $this->isInactive()) {
            return false;
        }

        // Only someone with Manage All may impersonate someone who has it.
        $impersonator = auth()->user();

        return ! ($impersonator && $this->hasPermissionTo(SystemPermission::ManageAll) && ! $impersonator->hasPermissionTo(SystemPermission::ManageAll));
    }

    /**
     * Check if the current session is under impersonation.
     */
    public function isImpersonated(): bool
    {
        return resolve('impersonate')->isImpersonating();
    }
}
