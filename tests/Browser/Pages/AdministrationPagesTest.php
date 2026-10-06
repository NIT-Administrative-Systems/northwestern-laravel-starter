<?php

declare(strict_types=1);

use App\Domains\Auth\Models\Role;
use App\Domains\Support\Models\Announcement;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\User\Models\Audit;
use App\Domains\User\Models\User;
use Northwestern\SysDev\Chassis\Testing\Browser\FilamentPages;

/*
 * Every administration page works and passes axe for a super administrator. Pages are found
 * from the panel; pages that need a record are checked below, each with a record made for it.
 */

beforeEach(fn () => $this->signInAsSuperAdministrator());

it('is healthy on every administration page', function (bool $dark) {
    if ($dark) {
        $this->useDarkMode();
    }

    expect(FilamentPages::in('administration'))->toAllBeHealthy();
})->with(['light' => false, 'dark' => true]);

it('is healthy on the app panel pages only administrators see', function (bool $dark) {
    if ($dark) {
        $this->useDarkMode();
    }

    expect(FilamentPages::in('app'))->toAllBeHealthy();
})->with(['light' => false, 'dark' => true]);

it("is healthy on the administration panel's dashboard on a phone", function () {
    expect(visit('/administration'))->toBeHealthyOnMobile();
});

// Pest calls each closure in the test, after sign-in, so every page gets its own record.
it('is healthy on record pages', function (string $path) {
    expect(visit($path))->toBeHealthyInEachTheme();
})->with([
    'a NetID user' => fn () => '/administration/users/' . User::factory()->create()->getKey(),
    'an API user' => fn () => '/administration/users/' . User::factory()->api()->create()->getKey(),
    'a role' => fn () => '/administration/roles/' . Role::factory()->create()->getKey(),
    "a role's edit page" => fn () => '/administration/roles/' . Role::factory()->create()->getKey() . '/edit',
    "a role's history" => fn () => '/administration/roles/' . Role::factory()->create()->getKey() . '/history',
    "an announcement's edit page" => fn () => '/administration/announcements/' . Announcement::factory()->create()->getKey() . '/edit',
    'a support ticket' => fn () => '/administration/support-tickets/' . SupportTicket::factory()->create()->getKey(),
]);

it('is healthy on an audit record', function () {
    $user = User::factory()->create();
    $user->forceFill(['first_name' => 'Accessibility'])->save();
    $audit = Audit::query()->latest('id')->firstOrFail();

    // The @pierre/diffs viewer renders syntax colors on its change backgrounds inside a shadow
    // DOM, and none of its themes meet color contrast there.
    expect(visit("/administration/audits/{$audit->getKey()}"))->toBeHealthyInEachTheme(exclude: ['diffs-container']);
});

it('is healthy on the consent screen for a self-registered MCP client', function () {
    $client = $this->postJson('/oauth/register', [
        'client_name' => 'Claude Code',
        'redirect_uris' => ['http://localhost:4100/callback'],
    ])->assertCreated()->json('client_id');

    $page = visit('/oauth/authorize?' . http_build_query([
        'client_id' => $client,
        'redirect_uri' => 'http://localhost:4100/callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'browser-test',
        'code_challenge' => 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
        'code_challenge_method' => 'S256',
    ]));

    expect($page->assertSee('Unverified AI client.'))->toBeHealthyInEachTheme();
});
