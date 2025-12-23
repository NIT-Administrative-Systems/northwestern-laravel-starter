<?php

declare(strict_types=1);

use App\Http\Controllers;
use Illuminate\Support\Facades\Route;

Route::get('/', Controllers\HomeController::class)->name('home');

if (config('platform.wildcard_photo_sync')) {
    Route::get('users/{user}/wildcard-photo', [App\Domains\User\Http\Controllers\WildcardPhotoController::class, 'show'])->name('users.wildcard-photo');
}

Route::prefix('platform')->name('platform.')->group(function () {
    Route::get('access-restricted', Controllers\Platform\EnvironmentLockdownController::class)->name('environment-lockdown');
});
