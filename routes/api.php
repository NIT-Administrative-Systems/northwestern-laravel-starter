<?php

declare(strict_types=1);

use App\Domains\Auth\Http\Controllers\Api\V1\AccessTokenApiController;
use App\Domains\Auth\Http\Middleware\AuthenticatesAccessTokens;
use App\Domains\Auth\Http\Middleware\LogsApiRequests;
use App\Domains\User\Http\Controllers\Api\V1\UserApiController;
use Illuminate\Support\Facades\Route;
use Northwestern\SysDev\Chassis\Http\Middleware\EnsureFeatureEnabled;
use Spatie\Health\Http\Controllers\HealthCheckJsonResultsController;
use Spatie\Health\Http\Middleware\RequiresSecretToken;

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
| Endpoints that do not require API authentication. Useful for publicly
| accessible resources.
*/

Route::middleware([EnsureFeatureEnabled::class . ':api.enabled'])->group(function () {
    //
});

/*
|--------------------------------------------------------------------------
| Protected API Routes
|--------------------------------------------------------------------------
| Endpoints that require access token authentication and are fully logged
| through the API request logging middleware.
*/

Route::middleware([EnsureFeatureEnabled::class . ':api.enabled', LogsApiRequests::class, AuthenticatesAccessTokens::class])->group(function () {
    Route::prefix('v1')->group(function () {
        Route::get('me', [UserApiController::class, 'me']);
        Route::get('me/tokens', [AccessTokenApiController::class, 'index']);
        Route::post('me/tokens', [AccessTokenApiController::class, 'store']);
        Route::get('me/tokens/{token}', [AccessTokenApiController::class, 'show']);
        Route::delete('me/tokens/{token}', [AccessTokenApiController::class, 'destroy']);
    });
});

/*
|--------------------------------------------------------------------------
| Health Check Routes
|--------------------------------------------------------------------------
| Protected by a secret token (X-Secret-Token header) rather than Bearer
| token authentication. Set HEALTH_SECRET_TOKEN in your .env file.
*/

Route::middleware([RequiresSecretToken::class])->group(function () {
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
