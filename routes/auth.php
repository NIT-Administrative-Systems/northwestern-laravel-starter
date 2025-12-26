<?php

declare(strict_types=1);

use App\Domains\Auth\Http\Controllers;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', Controllers\Local\ShowLoginCodeRequestController::class)->name('login-code.request');
        Route::post('login/request', [Controllers\Local\SendLoginCodeController::class, 'send'])
            ->middleware('throttle:login-code-request')
            ->name('login-code.send');
        Route::post('login/resend', [Controllers\Local\SendLoginCodeController::class, 'resend'])
            ->middleware('throttle:login-code-request')
            ->name('login-code.resend');
        Route::get('login/code', Controllers\Local\ShowLoginCodeFormController::class)->name('login-code.code');
        Route::post('login/verify', Controllers\Local\VerifyLoginCodeController::class)
            ->middleware('throttle:login-code-verify')
            ->name('login-code.verify');
    });

    Route::get('type', Controllers\LoginSelectionController::class)->name('login-selection');
    Route::post('logout', Controllers\LogoutSelectionController::class)->name('logout');

    Route::prefix('azure-ad')->group(function () {
        Route::get('redirect', [Controllers\WebSSOController::class, 'oauthRedirect'])->name('login-oauth-redirect');
        Route::post('callback', [Controllers\WebSSOController::class, 'oauthCallback'])->name('login-oauth-callback')
            ->withoutMiddleware([VerifyCsrfToken::class]);
        Route::get('oauth-logout', [Controllers\WebSSOController::class, 'oauthLogout'])->name('login-oauth-logout');
    });
});

Route::post('/impersonate/take/{id}/{guardName?}', [Controllers\ImpersonationController::class, 'take'])->name('impersonate');
Route::post('/impersonate/leave', [Controllers\ImpersonationController::class, 'leave'])->name('impersonate.leave');

Route::sentryTunnel(withoutMiddleware: [VerifyCsrfToken::class]);
