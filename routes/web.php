<?php

declare(strict_types=1);

use App\Http\Controllers;
use Illuminate\Support\Facades\Route;

Route::get('/', Controllers\HomeController::class)->name('home');

if (config('platform.wildcard_photo_sync')) {
    Route::get('users/{user}/wildcard-photo', [Controllers\UserController::class, 'showWildcardPhoto'])->name('users.wildcard-photo');
}
