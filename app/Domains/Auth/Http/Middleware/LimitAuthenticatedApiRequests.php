<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limits authenticated API requests per client (client credentials) or per user (every other
 * token), after {@see AuthenticatePassportToken} has identified who made the request.
 *
 * This is its own middleware rather than `throttle:<limiter>` because Laravel's middleware
 * priority runs every `ThrottleRequests` before authentication, when no one is identified yet.
 * The per-IP `throttle:api` limit keeps running there, ahead of authentication.
 */
class LimitAuthenticatedApiRequests
{
    private const int DECAY_SECONDS = 60;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'api:' . AuthenticatePassportToken::rateLimitKey($request);
        $maxAttempts = (int) config('rate-limiting.api.per_minute');

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new ThrottleRequestsException(headers: ['Retry-After' => RateLimiter::availableIn($key)]);
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($key, $maxAttempts));

        return $response;
    }
}
