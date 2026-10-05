<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Middleware;

use App\Domains\User\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses OAuth consent while an administrator is impersonating someone, so impersonation
 * never connects an application to the person's account. Applied to Passport's routes;
 * acts only on the authorization screen and its approve and deny endpoints.
 */
class RefuseOAuthConsentWhileImpersonating
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        abort_if($request->routeIs('passport.authorizations.*') && $user instanceof User && $user->isImpersonated(), 403, 'Applications cannot be connected while impersonating.');

        return $next($request);
    }
}
