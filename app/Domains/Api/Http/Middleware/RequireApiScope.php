<?php

declare(strict_types=1);

namespace App\Domains\Api\Http\Middleware;

use App\Domains\User\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Laravel\Passport\Exceptions\MissingScopeException;
use Northwestern\SysDev\Chassis\Enums\ApiPrincipalType;
use Northwestern\SysDev\Chassis\ValueObjects\ApiRequestContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires the request's token to carry an API scope, after
 * {@see AuthenticatePassportToken} has authenticated it:
 *
 * ```php
 * Route::get('users', UserIndexController::class)->middleware(RequireApiScope::class . ':view-users');
 * ```
 *
 * A token acting for a person can reach only what its scopes name,
 * whatever their permissions; their permissions and the route's
 * policy still apply on top. A service client acts as its API
 * user, whose roles are its ceiling, so it has every scope.
 */
class RequireApiScope
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        if (Context::get(ApiRequestContext::PRINCIPAL_TYPE) === ApiPrincipalType::Client->value) {
            return $next($request);
        }

        $user = $request->user();

        foreach ($scopes as $scope) {
            if (! $user instanceof User || ! $user->tokenCan($scope)) {
                throw new MissingScopeException($scope);
            }
        }

        return $next($request);
    }
}
