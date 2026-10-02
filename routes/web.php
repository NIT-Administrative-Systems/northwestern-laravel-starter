<?php

declare(strict_types=1);

use App\Http\Controllers;
use App\Http\Middleware\InjectLivewireAssets;
use App\Providers\Filament\AppPanelProvider;
use Illuminate\Support\Facades\Route;

/*
 * Public pages render on the public layout (`<x-layouts.public>`) in the app panel's
 * context, so they load its theme and can use Filament's Blade components.
 */
Route::middleware(['panel:' . AppPanelProvider::ID, InjectLivewireAssets::class])->group(function () {
    Route::get('/', Controllers\HomeController::class)->name('home');

    if (config('changelog.enabled')) {
        Route::prefix('support/changelog')->name('support.changelog.')->group(function () {
            Route::get('/', [Controllers\Support\ChangelogController::class, 'index'])->name('index');
            Route::get('{changelog}', [Controllers\Support\ChangelogController::class, 'show'])
                ->where('changelog', '^(?!feed\.rss$).+')
                ->name('show');
        });
    }
});

if (config('platform.wildcard_photo_sync')) {
    Route::get('users/{user}/wildcard-photo', [App\Domains\User\Http\Controllers\WildcardPhotoController::class, 'show'])->name('users.wildcard-photo');
}

if (config('changelog.enabled')) {
    // The feed is XML, not a page, so it doesn't need the public layout's panel context.
    Route::get('support/changelog/feed.rss', Controllers\Support\ChangelogFeedController::class)->name('support.changelog.feed');
}
