<?php

declare(strict_types=1);

use App\Domains\Auth\Actions\Applications\RegisterOAuthApplication;
use App\Domains\Support\Enums\AnnouncementSeverity;
use App\Domains\Support\Models\Announcement;
use Northwestern\SysDev\Chassis\Testing\Browser\FilamentPages;

/*
 * Every page a signed-in person without roles can reach works and passes axe. The app panel's
 * pages are found from the panel, so a new page is checked without being listed here.
 */

beforeEach(fn () => $this->signInAsNorthwesternUser());

it('is healthy on every app panel page', function (bool $dark) {
    if ($dark) {
        $this->useDarkMode();
    }

    expect([
        ...FilamentPages::in('app'),
        '/app/access-restricted',
        '/support/changelog',
        '/this-page-does-not-exist',
        '/administration',
    ])->toAllBeHealthy();
})->with(['light' => false, 'dark' => true]);

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

it('is healthy on the OAuth consent screen', function () {
    [, $client] = resolve(RegisterOAuthApplication::class)(
        'Reporting Tool',
        ['http://localhost:4100/callback'],
        false,
        ['view-users'],
        description: 'Weekly enrollment reports.',
    );

    $page = visit('/oauth/authorize?' . http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'http://localhost:4100/callback',
        'response_type' => 'code',
        'scope' => 'view-users',
        'state' => 'browser-test',
        'code_challenge' => 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
        'code_challenge_method' => 'S256',
    ]));

    expect($page->assertSee('Connect Reporting Tool')->assertSee('Approving returns you to'))->toBeHealthyInEachTheme();
});

it('is healthy on the OAuth consent screen for an internationalized domain', function () {
    [, $client] = resolve(RegisterOAuthApplication::class)('Reporting Tool', ['https://bücher.example/callback'], false, ['view-users']);

    $page = visit('/oauth/authorize?' . http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://bücher.example/callback',
        'response_type' => 'code',
        'scope' => 'view-users',
        'code_challenge' => 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
        'code_challenge_method' => 'S256',
    ]));

    expect($page->assertVisible('@internationalized-domain-warning'))->toBeHealthyInEachTheme();
});

it('is healthy on the page for an application that is no longer registered', function () {
    [, $client] = resolve(RegisterOAuthApplication::class)('Reporting Tool', ['http://localhost:4100/callback'], false, ['view-users']);
    $client->delete();

    $page = visit('/oauth/authorize?' . http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'http://localhost:4100/callback',
        'response_type' => 'code',
        'code_challenge' => 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
        'code_challenge_method' => 'S256',
    ]));

    expect($page->assertSee('Application Not Registered'))->toBeHealthyInEachTheme();
});
