<?php

declare(strict_types=1);

use App\Domains\Api\Http\Controllers\SwitchOAuthAccountController;
use App\Domains\Auth\Enums\SsoProvider;
use App\Domains\Auth\Http\Controllers;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use Northwestern\SysDev\Chassis\Http\Controllers\SentryTunnelController;

Route::prefix('auth')->group(function () {
    Route::post('logout', Controllers\LogoutSelectionController::class)->name('logout');

    // Only the provider people sign in with has routes.
    $provider = SsoProvider::configured();

    if ($provider === SsoProvider::EntraId) {
        Route::prefix('azure-ad')->group(function () {
            Route::get('redirect', [Controllers\WebSSOController::class, 'oauthRedirect'])->name('login-oauth-redirect');
            Route::post('callback', [Controllers\WebSSOController::class, 'oauthCallback'])->name('login-oauth-callback')
                ->withoutMiddleware([PreventRequestForgery::class]);
            Route::get('oauth-logout', [Controllers\WebSSOController::class, 'oauthLogout'])->name('login-oauth-logout');
        });
    }

    if ($provider === SsoProvider::OnlinePassport) {
        Route::prefix('websso')->group(function () {
            Route::get('login', [Controllers\WebSSOController::class, 'login'])->name('login-websso');
            Route::get('logout', [Controllers\WebSSOController::class, 'logout'])->name('login-websso-logout');
        });
    }
});

Route::post('oauth/switch-account', SwitchOAuthAccountController::class)->middleware('auth')->name('oauth.switch-account');

Route::post('/impersonate/take/{id}/{guardName?}', [Controllers\ImpersonationController::class, 'take'])->middleware('throttle:auth:impersonate')->name('impersonate');
Route::post('/impersonate/leave', [Controllers\ImpersonationController::class, 'leave'])->middleware('throttle:auth:impersonate')->name('impersonate.leave');

Route::post('sentry/tunnel', SentryTunnelController::class)
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('sentry.tunnel');
