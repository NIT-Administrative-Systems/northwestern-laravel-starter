<?php

declare(strict_types=1);

use App\Http\Controllers;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [Controllers\Auth\LoginLinkController::class, 'showRequestForm'])->name('login-link.request');
        Route::post('login/email', [Controllers\Auth\LoginLinkController::class, 'sendLink'])->name('login-link.send');
        Route::get('login/verify/{token}', [Controllers\Auth\LoginLinkController::class, 'verify'])->name('login-link.verify');
    });

    Route::get('type', Controllers\Auth\LoginSelectionController::class)->name('login-selection');
    Route::get('logout', Controllers\Auth\LogoutSelectionController::class)->name('logout');

    Route::prefix('azure-ad')->group(function () {
        Route::get('redirect', [Controllers\Auth\WebSSOController::class, 'oauthRedirect'])->name('login-oauth-redirect');
        Route::post('callback', [Controllers\Auth\WebSSOController::class, 'oauthCallback'])->name('login-oauth-callback')
            ->withoutMiddleware([VerifyCsrfToken::class]);
        Route::get('oauth-logout', [Controllers\Auth\WebSSOController::class, 'oauthLogout'])->name('login-oauth-logout');
    });
});

Route::get('/impersonate/take/{id}/{guardName?}', [Controllers\Admin\ImpersonationController::class, 'take'])->name('impersonate');
Route::get('/impersonate/leave', [Controllers\Admin\ImpersonationController::class, 'leave'])->name('impersonate.leave');

Route::sentryTunnel(withoutMiddleware: [VerifyCsrfToken::class]);
