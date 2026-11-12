<?php

declare(strict_types=1);

use App\Domains\Support\Enums\AnnouncementSeverity;
use App\Domains\Support\Models\Announcement;
use Northwestern\SysDev\Chassis\Testing\Browser\FilamentPages;

/*
 * Every page a signed-in person without roles can reach works and passes axe.
 *
 * The app panel's pages are found from the panel, so
 * a new page is checked without being listed here.
 */

beforeEach(fn () => $this->signInAsNorthwesternUser());

it('is healthy on every app panel page', function (bool $dark) {
    if ($dark) {
        $this->useDarkMode();
    }

    expect([
        ...FilamentPages::in('app'),
        '/support/changelog',
        '/this-page-does-not-exist',
        '/administration',
    ])->toAllBeHealthy();
})->with(['light' => false, 'dark' => true]);

it('is healthy on the lockdown page', function () {
    config(['platform.lockdown.enabled' => true]);

    expect(visit('/app/access-restricted')->assertSee('Access Restricted'))->toBeHealthyInEachTheme();
});

it("is healthy on the app panel's dashboard on a phone", function () {
    expect(visit('/app'))->toBeHealthyOnMobile();
});

it('is healthy with the announcement banner and on the Announcements page', function () {
    Announcement::factory()->severity(AnnouncementSeverity::Warning)->create([
        'title' => 'Scheduled Maintenance',
        'body' => 'The application is **unavailable** Saturday from 6 to 8 a.m.',
    ]);
    Announcement::factory()->create();

    expect(visit('/app')->assertVisible('@announcement-banner'))->toBeHealthyInEachTheme();
    expect(visit('/app/announcements')->assertSee('Scheduled Maintenance'))->toBeHealthyInEachTheme();
});
