<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Northwestern\SysDev\Chassis\Http\Middleware\EnvironmentLockdown as BaseEnvironmentLockdown;

/**
 * Restricts application access to users with assigned roles beyond the default Northwestern User role.
 *
 * This middleware is typically enabled in non-production environments (staging, demo)
 * to prevent unauthorized users who discover the application URL from accessing it.
 * Users with only the default Northwestern User role (or no roles) are redirected
 * to a lockdown page explaining they need to be granted access by an administrator.
 *
 * ## Exemptions
 *
 * The following requests bypass lockdown:
 * - Lockdown disabled via config
 * - User is impersonating another user
 * - User has at least one role besides Northwestern User
 * - Request is to an authentication or lockdown route
 */
class EnvironmentLockdown extends BaseEnvironmentLockdown
{
    protected function isEnabled(): bool
    {
        return (bool) config('platform.lockdown.enabled');
    }

    protected function isAuthorized(Request $request): bool
    {
        return $request->user()->isImpersonated()
            || $request->user()->non_default_roles->isNotEmpty();
    }

    protected function redirectRoute(): string
    {
        return 'platform.environment-lockdown';
    }

    protected function exemptedRoutePatterns(): array
    {
        return config('platform.lockdown.exempted_routes', []);
    }
}
