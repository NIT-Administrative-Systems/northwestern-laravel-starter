<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Middleware;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\User\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only a person with the `UseMcp` permission may connect an MCP client, and none can while
 * the MCP server is off. Applied to Passport's routes; acts only on the authorization screen
 * for a self-registered client. Approving needs that screen, so it is covered too.
 */
class RequireMcpPermissionForConsent
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('passport.authorizations.authorize')) {
            return $next($request);
        }

        $clientId = $request->string('client_id')->toString();
        // Passport answers an unknown or malformed client ID itself.
        $client = Str::isUuid($clientId) ? OAuthClient::query()->find($clientId) : null;

        if (! $client instanceof OAuthClient || ! $client->isMcpClient()) {
            return $next($request);
        }

        abort_unless((bool) config('mcp.enabled'), 404);

        $user = $request->user('web');

        abort_if($user instanceof User && ! $user->can(SystemPermission::UseMcp), 403, 'You don\'t have permission to connect AI clients. Ask an administrator for the Use MCP permission.');

        return $next($request);
    }
}
