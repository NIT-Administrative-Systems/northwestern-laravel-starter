<?php

declare(strict_types=1);

use App\Http\Controllers;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', Controllers\Auth\Local\ShowLoginCodeRequestController::class)->name('login-code.request');
        Route::post('login/request', Controllers\Auth\Local\SendLoginCodeController::class)
            ->middleware('throttle:login-code-request')
            ->name('login-code.send');
        Route::post('login/resend', Controllers\Auth\Local\ResendLoginCodeController::class)
            ->middleware('throttle:login-code-request')
            ->name('login-code.resend');
        Route::get('login/code', Controllers\Auth\Local\ShowLoginCodeFormController::class)->name('login-code.code');
        Route::post('login/verify', Controllers\Auth\Local\VerifyLoginCodeController::class)
            ->middleware('throttle:login-code-verify')
            ->name('login-code.verify');
    });

    Route::get('type', Controllers\Auth\LoginSelectionController::class)->name('login-selection');
    Route::post('logout', Controllers\Auth\LogoutSelectionController::class)->name('logout');

    Route::prefix('azure-ad')->group(function () {
        Route::get('redirect', [Controllers\Auth\WebSSOController::class, 'oauthRedirect'])->name('login-oauth-redirect');
        Route::post('callback', [Controllers\Auth\WebSSOController::class, 'oauthCallback'])->name('login-oauth-callback')
            ->withoutMiddleware([VerifyCsrfToken::class]);
        Route::get('oauth-logout', [Controllers\Auth\WebSSOController::class, 'oauthLogout'])->name('login-oauth-logout');
    });
});

Route::get('/impersonate/take/{id}/{guardName?}', [Controllers\Auth\ImpersonationController::class, 'take'])->name('impersonate');
Route::get('/impersonate/leave', [Controllers\Auth\ImpersonationController::class, 'leave'])->name('impersonate.leave');

Route::sentryTunnel(withoutMiddleware: [VerifyCsrfToken::class]);
