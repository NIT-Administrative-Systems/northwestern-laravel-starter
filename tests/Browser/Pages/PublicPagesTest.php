<?php

declare(strict_types=1);

use App\Domains\Auth\Exceptions\AccountDeactivated;
use App\Domains\Support\Models\Changelog;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\Route;
use Lab404\Impersonate\Services\ImpersonateManager;
use Mockery\MockInterface;
use Northwestern\SysDev\Chassis\Testing\Browser\ErrorPages;
use Northwestern\SysDev\Chassis\Testing\Browser\Layout;

/*
 * Every page a guest can reach works and passes axe,
 * in light and dark mode and at a phone's width.
 *
 * Add your application's public pages to the dataset.
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

// Every page before signing in is light; dark mode starts inside the panel.
it('keeps the sign-in pages light in dark mode, also after navigating between them', function () {
    $page = visit('/app/login')->inDarkMode();

    expect($page->script("document.documentElement.classList.contains('dark')"))->toBeFalse();

    $page->click('@email-login')->assertVisible('@email-input');

    expect($page->script("document.documentElement.classList.contains('dark')"))->toBeFalse();
});

it('is healthy with the impersonation banner, in each theme and on a phone', function () {
    $this->signInAsNorthwesternUser();
    // A browser test can't seed the session, so the manager reports an impersonation.
    $this->partialMock(ImpersonateManager::class, function (MockInterface $mock): void {
        $mock->shouldReceive('getCurrentAuthGuardName')->andReturn('web');
        $mock->shouldReceive('isImpersonating')->andReturn(true);
    });

    expect(visit('/this-page-does-not-exist')->assertSee('Leave Impersonation'))
        ->toBeHealthyInEachTheme()
        ->toBeHealthyOnMobile();
});

it('is healthy with a unit lockup in the header', function () {
    // Any image in public/ stands in for a unit's lockup;
    // an outside host would make the test depend on it.
    config(['northwestern-filament-theme.lockup' => 'images/default-profile-photo.svg']);

    expect(visit('/')->assertVisible('@site-header-lockup'))->toBeHealthyInEachTheme();
});

it('is healthy on a changelog entry', function () {
    $entry = Changelog::factory()->create(['body' => "### Changes\n\n- A change"]);

    expect(visit("/support/changelog/{$entry->slug}"))->toBeHealthyInEachTheme();
});

it('is healthy on each error page', function (int $status) {
    expect(visit(ErrorPages::path($status)))->toBeHealthyInEachTheme();
})->with([401, 402, 403, 404, 419, 429, 500, 503]);

it('is healthy on the database-paused page', function () {
    Route::get('/database-paused', fn () => response()->view('errors.database-paused', [], 503))->middleware('web');

    expect(visit('/database-paused'))->toBeHealthyInEachTheme();
});

// Shown when a deactivated account signs in, by any method.
it('is healthy on the account deactivated page', function () {
    Route::get('/account-deactivated', fn () => throw new AccountDeactivated())->middleware('web');

    expect(visit('/account-deactivated')->assertSee('Account Deactivated'))
        ->toBeHealthyInEachTheme()
        ->toBeHealthyOnMobile();
});

it('fits a 320 pixel phone, with controls a finger can tap', function () {
    User::factory()->affiliate()->create(['email' => 'partner@example.com']);
    Changelog::factory()->create();
    $paths = [
        '/',
        '/app/login',
        '/app/login/email',
        '/support/changelog',
        ...array_map(ErrorPages::path(...), [401, 402, 403, 404, 419, 429, 500, 503]),
    ];

    expect($paths)->toAllFitEachWidth(widths: [320], tapTargets: true);

    $page = Layout::visit('/app/login/email', width: 320)
        ->type('@email-input', 'partner@example.com')
        ->click('@continue-button')
        ->assertVisible('@code-input');

    expect($page)->toFitTheScreen(tapTargets: true);
});
