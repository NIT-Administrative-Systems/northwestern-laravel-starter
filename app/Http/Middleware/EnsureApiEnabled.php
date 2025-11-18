<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Ensures the API is enabled before processing requests.
 *
 * When the API is disabled via configuration, all API routes will return
 * a 503 Service Unavailable response. This is useful for maintenance
 * windows or temporarily disabling API access.
 */
class EnsureApiEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('auth.api.enabled')) {
            throw new ServiceUnavailableHttpException();
        }

        return $next($request);
    }
}
