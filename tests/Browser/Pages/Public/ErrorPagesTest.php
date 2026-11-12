<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Northwestern\SysDev\Chassis\Testing\Browser\ErrorPages;

/*
 * Every error page works and passes axe in each theme.
 *
 * Add a new error page's status to the dataset.
 */

it('is healthy on each error page', function (int $status) {
    expect(visit(ErrorPages::path($status)))->toBeHealthyInEachTheme();
})->with([401, 402, 403, 404, 419, 429, 500, 503]);

it('is healthy on the database-paused page', function () {
    Route::get('/database-paused', fn () => response()->view('errors.database-paused', [], 503))->middleware('web');

    expect(visit('/database-paused'))->toBeHealthyInEachTheme();
});
