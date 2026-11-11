<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\User\Models\User;
use Illuminate\Http\Request;
use Northwestern\SysDev\Chassis\Http\Middleware\EnvironmentLockdown as BaseEnvironmentLockdown;

/**
 * Keeps a locked-down environment, such as staging or a demo, to people with a
 * role beyond Northwestern User. Everyone else is sent to the lockdown page.
 *
 * A request passes through while impersonating, for the person's own
 * photo, or for a route in `platform.lockdown.exempted_routes`.
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
            || $request->user()->non_default_roles->isNotEmpty()
            || $this->isOwnPhoto($request);
    }

    protected function redirectRoute(): string
    {
        return 'filament.app.environment-lockdown';
    }

    protected function exemptedRoutePatterns(): array
    {
        return config('platform.lockdown.exempted_routes', []);
    }

    /**
     * Determine whether the request is for the signed-in person's own photo.
     *
     * The lockdown page shows it in the top bar. Anyone else's photo
     * stays locked down, so this is not one of the exempted routes.
     */
    private function isOwnPhoto(Request $request): bool
    {
        if (! $request->routeIs('users.wildcard-photo')) {
            return false;
        }

        $user = $request->route('user');

        return (string) ($user instanceof User ? $user->getKey() : $user) === (string) $request->user()->getKey();
    }
}
