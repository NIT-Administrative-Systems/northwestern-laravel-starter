<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticatesAccessTokens;
use App\Http\Middleware\EnsureApiEnabled;
use App\Http\Middleware\LogsApiRequests;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
| Endpoints that do not require API authentication. Useful for health checks
| or other publicly accessible resources.
*/

Route::middleware([EnsureApiEnabled::class])->group(function () {
    Route::get('health', Spatie\Health\Http\Controllers\HealthCheckJsonResultsController::class);
});

/*
|--------------------------------------------------------------------------
| Protected API Routes
|--------------------------------------------------------------------------
| Endpoints that require access token authentication and are fully logged
| through the API request logging middleware.
*/

Route::middleware([EnsureApiEnabled::class, LogsApiRequests::class, AuthenticatesAccessTokens::class])->group(function () {
    //
});

/*
|--------------------------------------------------------------------------
| EventHub Webhook Routes
|--------------------------------------------------------------------------
| Endpoints used by Northwestern's EventHub for inbound event delivery.
*/

Route::middleware(['eventhub_hmac'])->prefix('eventhub')->group(function () {
    // Uncomment the following route if your project is subscribed to the `etidentity.ldap.netid.term` topic.
    // Route::post('netid-update', App\Http\Controllers\Webhooks\NetIdUpdateController::class)->eventHubWebhook('etidentity.ldap.netid.term')->name('netid-update');
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
