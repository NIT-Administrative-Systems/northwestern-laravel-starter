<?php

declare(strict_types=1);

use App\Domains\Auth\Enums\TokenAudience;
use App\Domains\Auth\Http\Middleware\AuthenticatePassportToken;
use App\Domains\Auth\Http\Middleware\LimitAuthenticatedApiRequests;
use App\Domains\Auth\Http\Middleware\LogsApiRequests;
use App\Domains\Auth\Http\Middleware\RequireTokenAudience;
use App\Domains\User\Http\Controllers\Api\V1\UserApiController;
use Illuminate\Support\Facades\Route;
use Northwestern\SysDev\Chassis\Http\Middleware\EnsureFeatureEnabled;
use Northwestern\SysDev\Chassis\Http\Middleware\RequireSecretToken;
use Spatie\Health\Http\Controllers\HealthCheckJsonResultsController;

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
| Endpoints that do not require API authentication. Useful for publicly
| accessible resources.
*/

Route::middleware([EnsureFeatureEnabled::class . ':api.enabled,404'])->group(function () {
    //
});

/*
|--------------------------------------------------------------------------
| Protected API Routes
|--------------------------------------------------------------------------
| Endpoints that require a Passport access token, fully logged through the API
| request logging middleware and rate limited per client or user once the token is
| known. Credentials are never created here: service clients are created in
| Administration and personal access tokens on the Account page. Tokens issued
| to MCP clients are refused: they work only on the MCP server (routes/ai.php).
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
| Protected by a secret token (X-Secret-Token header) rather than Bearer
| token authentication. The endpoint refuses every request until
| HEALTH_SECRET_TOKEN is set.
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
    // Uncomment the following route if your project is subscribed to the `etidentity.ldap.netid.term` topic.
    // Route::post('netid-update', App\Domains\User\Http\Controllers\Webhooks\NetIdUpdateController::class)->eventHubWebhook('etidentity.ldap.netid.term')->name('netid-update');
});

/*
|--------------------------------------------------------------------------
| Mock API Routes
|--------------------------------------------------------------------------
| Local-only mock implementations of external services used for integration
| testing and offline development.
*/

// Route::prefix('mock')->withoutMiddleware(ThrottleRequests::class.':api')->group(function () {
//
// });
