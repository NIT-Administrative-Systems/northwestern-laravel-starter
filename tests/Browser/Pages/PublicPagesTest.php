<?php

declare(strict_types=1);

use App\Domains\Support\Models\Changelog;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\Route;
use Northwestern\SysDev\Chassis\Testing\Browser\ErrorPages;

/*
 * Every page a guest can reach works and passes axe, in light and dark mode and at a phone's
 * width. Add your application's public pages to the dataset.
 */

dataset('public pages', [
    'landing' => '/',
    'sign-in' => '/app/login',
    'email sign-in' => '/app/login/email',
    'changelog' => '/support/changelog',
    'not found' => '/this-page-does-not-exist',
]);

it('is healthy in each theme and on a phone', function (string $path) {
    expect(visit($path))
        ->toBeHealthyInEachTheme()
        ->toBeHealthyOnMobile();
})->with('public pages');

it('is healthy at the email sign-in code step', function (bool $dark) {
    User::factory()->affiliate()->create(['email' => 'partner@example.com']);

    // inDarkMode() returns a new page rather than changing this one.
    $page = $dark ? visit('/app/login/email')->inDarkMode() : visit('/app/login/email');

    $page->type('@email-input', 'partner@example.com')
        ->click('@continue-button')
        ->assertVisible('@code-input');

    expect($page)->toBeHealthy();
})->with(['light' => false, 'dark' => true]);

it('is healthy on a changelog entry', function () {
    $entry = Changelog::factory()->create(['body' => "### Changes\n\n- A change"]);

    expect(visit("/support/changelog/{$entry->slug}"))->toBeHealthyInEachTheme();
});

it('is healthy on each error page', function (int $status) {
    expect(visit(ErrorPages::path($status)))->toBeHealthyInEachTheme();
})->with([401, 402, 403, 404, 419, 429, 500, 503]);

it('is healthy on the database-paused page', function () {
    Route::get('/database-paused', fn () => response()->view('errors.database-paused', [], 500))->middleware('web');

    expect(visit('/database-paused'))->toBeHealthyInEachTheme();
});
