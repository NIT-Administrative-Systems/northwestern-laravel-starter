<?php

declare(strict_types=1);

namespace App\Domains\Api\Services;

use App\Domains\Api\Http\Middleware\AuthenticatePassportToken;
use Illuminate\Routing\Router;

/**
 * Inspects the registered routes to determine whether any API endpoint
 * is protected by the {@see AuthenticatePassportToken} middleware.
 *
 * API Requests uses it to decide whether to warn that no API routes are protected.
 */
readonly class ApiRouteInspector
{
    public function __construct(
        private Router $router,
    ) {
    }

    public function hasProtectedRoutes(): bool
    {
        /** @phpstan-ignore-next-line  */
        foreach ($this->router->getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with((string) $uri, 'api')) {
                continue;
            }

            if (in_array(AuthenticatePassportToken::class, $route->gatherMiddleware(), true)) {
                return true;
            }
        }

        return false;
    }
}
