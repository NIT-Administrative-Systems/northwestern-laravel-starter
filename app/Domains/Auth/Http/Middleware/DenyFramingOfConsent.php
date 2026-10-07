<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops other sites from showing the OAuth consent screen in a frame, where they could trick a
 * person into pressing Approve (clickjacking). Applied to Passport's routes; acts on the
 * authorization screen and its approve and deny responses, including their error pages.
 */
class DenyFramingOfConsent
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->routeIs('passport.authorizations.*')) {
            $response->headers->set('X-Frame-Options', 'DENY');
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'");
        }

        return $response;
    }
}
