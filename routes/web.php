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
});

Route::prefix('platform')->name('platform.')->group(function () {
    Route::get('access-restricted', Controllers\Platform\EnvironmentLockdownController::class)->name('environment-lockdown');
});
