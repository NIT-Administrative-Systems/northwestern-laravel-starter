<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
 *
 * @see \App\Http\Controllers\Platform\EnvironmentLockdownController
 */
class EnvironmentLockdown
{
    /**
     * Routes that are exempt from environment lockdown restrictions.
     */
    public const array EXEMPTED_ROUTES = [
        // Authentication
        'login-oauth-redirect',
        'login-oauth-callback',
        'login-oauth-logout',
        'login-selection',
        'logout',
        'login-code.request',
        'login-code.send',
        'login-code.verify',
        'login-code.code',
        'login-code.resend',

        // Impersonation
        'impersonate',
        'impersonate.leave',

        // Lockdown Page
        'platform.environment-lockdown',
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip lockdown if the feature is disabled
        if (! config('platform.lockdown.enabled')) {
            return $next($request);
        }

        // Allow guests through so they can log in
        if (! $request->user()) {
            return $next($request);
        }

        // Allow impersonators through (they already have access)
        if ($request->user()->isImpersonated()) {
            return $next($request);
        }

        // Allow users with non-default roles through
        if ($request->user()->non_default_roles->isNotEmpty()) {
            return $next($request);
        }

        // Allow access to authentication and lockdown routes
        if ($request->routeIs(self::EXEMPTED_ROUTES)) {
            return $next($request);
        }

        // Redirect users without roles to lockdown page
        return redirect()->route('platform.environment-lockdown');
    }
}
