<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Requires the `X-Secret-Token` header on the JSON health endpoint.
 *
 * Spatie's own `RequiresSecretToken` lets every request through while `HEALTH_SECRET_TOKEN`
 * is empty, which is how `.env.example` ships it, so the endpoint would be public by default.
 * This one refuses every request until a token is set.
 */
class RequireHealthSecretToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $secretToken = config('health.secret_token');

        if (! is_string($secretToken) || $secretToken === '') {
            throw new AccessDeniedHttpException('The health endpoint is disabled until HEALTH_SECRET_TOKEN is set.');
        }

        if (! hash_equals($secretToken, (string) $request->header('X-Secret-Token'))) {
            throw new AccessDeniedHttpException('Incorrect secret token.');
        }

        return $next($request);
    }
}
