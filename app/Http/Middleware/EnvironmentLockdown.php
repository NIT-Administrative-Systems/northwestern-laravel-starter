<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Northwestern\SysDev\Chassis\Http\Middleware\EnvironmentLockdown as BaseEnvironmentLockdown;

/**
 * Keeps a locked-down environment, such as staging or a demo, to people with a
 * role beyond Northwestern User. Everyone else is sent to the lockdown page.
 *
 * Impersonation and `platform.lockdown.exempted_routes` pass through.
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
        return 'filament.app.environment-lockdown';
    }

    protected function exemptedRoutePatterns(): array
    {
        return config('platform.lockdown.exempted_routes', []);
    }
}
