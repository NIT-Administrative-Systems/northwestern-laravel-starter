<?php

declare(strict_types=1);

use App\Http\Controllers;
use Illuminate\Support\Facades\Route;

Route::get('/', Controllers\HomeController::class)->name('home');

if (config('platform.wildcard_photo_sync')) {
    Route::get('users/{user}/wildcard-photo', [App\Domains\User\Http\Controllers\WildcardPhotoController::class, 'show'])->name('users.wildcard-photo');
}

Route::prefix('support')->name('support.')->group(function () {
    if (config('changelog.enabled')) {
        Route::prefix('changelog')->name('changelog.')->group(function () {
            Route::get('/', [Controllers\Support\ChangelogController::class, 'index'])->name('index');
            Route::get('feed.rss', Controllers\Support\ChangelogFeedController::class)->name('feed');
            Route::get('{changelog}', [Controllers\Support\ChangelogController::class, 'show'])->name('show');
        });
    }

    if (config('support.enabled')) {
        Route::middleware('auth')->group(function () {
            Route::get('contact', [Controllers\Support\ContactController::class, 'create'])->name('contact.create');
            Route::post('contact', [Controllers\Support\ContactController::class, 'store'])->middleware('throttle:support-submission')->name('contact.store');
        });
    }
});

Route::prefix('platform')->name('platform.')->group(function () {
    Route::get('access-restricted', Controllers\Platform\EnvironmentLockdownController::class)->name('environment-lockdown');
});
