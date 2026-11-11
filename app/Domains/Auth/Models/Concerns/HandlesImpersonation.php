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

        $impersonator = auth()->user();

        return ! $impersonator instanceof User || $impersonator->holdsEveryPermissionOf($this);
    }

    /**
     * Check if the current session is under impersonation.
     */
    public function isImpersonated(): bool
    {
        return resolve('impersonate')->isImpersonating();
    }

    /**
     * Determine whether this user holds every permission another user holds.
     *
     * Acting as someone with a permission the impersonator lacks would lend
     * it to them: they could use it to give their own account a new role.
     * Manage All passes every permission check, so it covers the rest.
     */
    private function holdsEveryPermissionOf(User $target): bool
    {
        if ($this->hasPermissionTo(SystemPermission::ManageAll)) {
            return true;
        }

        return $target->getAllPermissions()->pluck('name')
            ->diff($this->getAllPermissions()->pluck('name'))
            ->isEmpty();
    }
}
