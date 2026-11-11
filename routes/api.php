<?php

declare(strict_types=1);

use App\Domains\Api\Enums\TokenAudience;
use App\Domains\Api\Http\Middleware\AuthenticatePassportToken;
use App\Domains\Api\Http\Middleware\LimitAuthenticatedApiRequests;
use App\Domains\Api\Http\Middleware\LogsApiRequests;
use App\Domains\Api\Http\Middleware\RequireTokenAudience;
use App\Domains\User\Http\Controllers\Api\V1\UserApiController;
use Illuminate\Support\Facades\Route;
use Northwestern\SysDev\Chassis\Http\Middleware\EnsureFeatureEnabled;
use Northwestern\SysDev\Chassis\Http\Middleware\RequireSecretToken;
use Spatie\Health\Http\Controllers\HealthCheckJsonResultsController;

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
| Endpoints that need no authentication, for anything that's public.
*/

Route::middleware([EnsureFeatureEnabled::class . ':api.enabled,404'])->group(function () {
    //
});

/*
|--------------------------------------------------------------------------
| Protected API Routes
|--------------------------------------------------------------------------
| Endpoints that need a Passport access token. Every request is logged,
| and it's rate limited per client or user once the token is known.
|
| Nothing here creates credentials: Administration creates service
| clients, and the Account page creates personal access tokens.
|
| Tokens issued to MCP clients are refused; they
| work only on the MCP server (routes/ai.php).
*/

Route::middleware([
    EnsureFeatureEnabled::class . ':api.enabled,404',
    LogsApiRequests::class,
    AuthenticatePassportToken::class,
    RequireTokenAudience::for(TokenAudience::Api),
    LimitAuthenticatedApiRequests::class,
])->group(function () {
    Route::prefix('v1')->group(function () {
        Route::get('me', [UserApiController::class, 'me']);
    });
});

/*
|--------------------------------------------------------------------------
| Health Check Routes
|--------------------------------------------------------------------------
| Protected by a secret in the X-Secret-Token header, not a bearer token.
| The endpoint refuses every request until HEALTH_SECRET_TOKEN is set.
*/

Route::middleware([RequireSecretToken::class . ':health.secret_token'])->group(function () {
    Route::get('health', HealthCheckJsonResultsController::class);
});

/*
|--------------------------------------------------------------------------
| EventHub Webhook Routes
|--------------------------------------------------------------------------
| Endpoints used by Northwestern's EventHub for inbound event delivery.
*/

Route::middleware(['eventhub_hmac'])->prefix('eventhub')->group(function () {
    // Uncomment this if the application subscribes to `etidentity.ldap.netid.term`.
    // Route::post('netid-update', App\Domains\User\Http\Controllers\Webhooks\NetIdUpdateController::class)->eventHubWebhook('etidentity.ldap.netid.term')->name('netid-update');
});

/*
|--------------------------------------------------------------------------
| Mock API Routes
|--------------------------------------------------------------------------
| Local stand-ins for outside services, for integration tests and offline work.
*/

// Route::prefix('mock')->withoutMiddleware(ThrottleRequests::class.':api')->group(function () {
//
// });
