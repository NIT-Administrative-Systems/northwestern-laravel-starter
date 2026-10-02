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

Route::prefix('support')->name('support.')->group(function () {
    if (config('changelog.enabled')) {
        Route::get('changelog/feed.rss', Controllers\Support\ChangelogFeedController::class)->name('changelog.feed');
    }

    if (config('support.enabled')) {
        Route::middleware('auth')->group(function () {
            Route::get('contact', [Controllers\Support\ContactController::class, 'create'])->name('contact.create');
            Route::post('contact', [Controllers\Support\ContactController::class, 'store'])->middleware('throttle:support:contact')->name('contact.store');
        });
    }
});
