<?php

declare(strict_types=1);

use App\Domains\Auth\Enums\TokenAudience;
use App\Domains\Auth\Http\Middleware\AuthenticatePassportToken;
use App\Domains\Auth\Http\Middleware\LogsApiRequests;
use App\Domains\Auth\Http\Middleware\RequireTokenAudience;
use App\Mcp\Http\Controllers\OAuthDiscoveryController;
use App\Mcp\Http\Controllers\RegisterClientController;
use App\Mcp\Servers\AppServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Northwestern\SysDev\Chassis\Http\Middleware\EnsureFeatureEnabled;

/*
|--------------------------------------------------------------------------
| MCP Server
|--------------------------------------------------------------------------
| The MCP server, the OAuth discovery documents an MCP client reads to connect,
| and the registration endpoint it registers itself at. All answer 404 while
| `mcp.enabled` is off. Loaded by laravel/mcp.
|
| A client connects as a person: it registers, the person approves it on the
| consent screen (with the UseMcp permission), and its token carries only
| `mcp:use`, which the REST API refuses. The connection's wiring (the challenge's
| scope and the rate limits) is in App\Mcp\McpServiceProvider.
*/

Route::middleware([EnsureFeatureEnabled::class . ':mcp.enabled,404', 'throttle:api'])->group(function () {
    Route::get('.well-known/oauth-protected-resource', [OAuthDiscoveryController::class, 'protectedResource'])
        ->name('mcp.oauth.protected-resource');
    // Named as laravel/mcp expects, so its 401 response points clients here.
    Route::get('.well-known/oauth-protected-resource/{path}', [OAuthDiscoveryController::class, 'protectedResource'])
        ->where('path', 'mcp')
        ->name('mcp.oauth.protected-resource.nested');
    Route::get('.well-known/oauth-authorization-server/{path?}', [OAuthDiscoveryController::class, 'authorizationServer'])
        ->where('path', 'mcp')
        ->name('mcp.oauth.authorization-server');

    Route::post('oauth/register', RegisterClientController::class)
        ->middleware('throttle:mcp-registration')
        ->name('mcp.oauth.register');

    Mcp::web('mcp', AppServer::class)
        ->middleware([
            LogsApiRequests::class,
            AuthenticatePassportToken::class,
            RequireTokenAudience::for(TokenAudience::Mcp),
            'throttle:mcp-tool-calls',
        ])
        ->name('mcp.server');
});
