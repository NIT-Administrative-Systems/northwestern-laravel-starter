<?php

declare(strict_types=1);

namespace App\Domains\Core\Services;

use App\Domains\Auth\Http\Middleware\AuthenticatesAccessTokens;
use Illuminate\Routing\Router;

/**
 * Inspects the application's registered routes to determine whether any API endpoints
 * are protected by the {@see AuthenticatesAccessTokens} middleware.
 *
 * This is used by the **API Request Logging** dashboard to decide whether a warning
 * should be shown indicating that no protected API routes exist.
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

            if (in_array(AuthenticatesAccessTokens::class, $route->gatherMiddleware(), true)) {
                return true;
            }
        }

        return false;
    }
}
