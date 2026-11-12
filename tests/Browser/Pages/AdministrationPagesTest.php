<?php

declare(strict_types=1);

use App\Domains\Access\Enums\RoleTypeEnum;
use App\Domains\Access\Models\Role;
use App\Domains\Api\Actions\ServiceClients\CreateServiceClient;
use App\Domains\Core\Models\Audit;
use App\Domains\Support\Models\Announcement;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginRecord;
use Northwestern\SysDev\Chassis\Testing\Browser\FilamentPages;

/*
 * Every administration page works and passes axe for a super administrator.
 *
 * Pages are found from the panel, and pages that need a
 * record are checked below, each with its own record.
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

// Pest calls each closure in the test, after sign-in, so every page gets a record.
it('is healthy on record pages', function (string $path) {
    expect(visit($path))->toBeHealthyInEachTheme();
})->with([
    'a NetID user' => fn () => '/administration/users/' . User::factory()->create()->getKey(),
    "a NetID user's access" => fn () => '/administration/users/' . User::factory()->create()->getKey() . '/access',
    "a NetID user's activity" => fn () => '/administration/users/' . User::factory()->create()->getKey() . '/activity',
    "a NetID user's API credentials" => fn () => '/administration/users/' . User::factory()->create()->getKey() . '/api-credentials',
    'an API user' => fn () => '/administration/users/' . User::factory()->api()->create()->getKey(),
    "an API user's credentials" => fn () => '/administration/users/' . User::factory()->api()->create()->getKey() . '/api-credentials',
    'a deactivated user' => fn () => '/administration/users/' . tap(User::factory()->create())->delete()->getKey(),
    'a role' => fn () => '/administration/roles/' . Role::factory()->create()->getKey(),
    "a role's edit page" => fn () => '/administration/roles/' . Role::factory()->create()->getKey() . '/edit',
    "a role's history" => fn () => '/administration/roles/' . Role::factory()->create()->getKey() . '/history',
    "an announcement's edit page" => fn () => '/administration/announcements/' . Announcement::factory()->create()->getKey() . '/edit',
    'a support ticket' => fn () => '/administration/support-tickets/' . SupportTicket::factory()->create()->getKey(),
]);

// The modals that add people to a role and deactivate an account.
it('is healthy with its action modals open', function (string $path, string $menu, string $action, string $content) {
    $page = visit($path);

    if ($menu !== '') {
        $page->click($menu);
    }

    expect($page->click($action)->assertSee($content))->toBeHealthyInEachTheme();
})->with([
    'pasting people on a role' => fn () => ['/administration/roles/' . Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create()->getKey(), 'Add People', 'Paste NetIDs or Emails', 'NetIDs or Email Addresses'],
    'uploading people on the Users list' => fn () => ['/administration/users', 'Add People to a Role', 'Upload a CSV File', 'Add People from a CSV File'],
    'deactivating an account' => fn () => ['/administration/users/' . User::factory()->create()->getKey(), '', 'Deactivate', 'Deactivate Account'],
]);

it('is healthy on an audit record', function () {
    $user = User::factory()->create();
    $user->forceFill(['first_name' => 'Accessibility'])->save();
    $audit = Audit::query()->latest('id')->firstOrFail();

    // The @pierre/diffs viewer renders syntax colors on its change backgrounds
    // inside a shadow DOM, and none of its themes meet color contrast there.
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

// Long names, emails, job titles and role names are what break a layout.
it('fits every administration page and its tables at each width', function () {
    $person = User::factory()->create([
        'first_name' => 'Maximiliana Alexandrina Josephine',
        'last_name' => 'Featherstonehaugh-Wolstenholme',
        'email' => 'maximiliana.featherstonehaugh-wolstenholme@northwestern.edu',
        'job_titles' => ['Associate Director of Strategic Enrollment Communications and Outreach'],
        'departments' => ['Feinberg School of Medicine Department of Preventive Medicine'],
    ]);
    $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create(['name' => 'Graduate Program Coordinators for the School of Professional Studies']);
    $role->users()->attach($person);
    UserLoginRecord::factory()->for($person)->create();
    Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create(['name' => 'enrollment-communications-and-outreach-reviewers'])->users()->attach($person);
    Announcement::factory()->create(['title' => 'Scheduled maintenance of the enrollment reporting warehouse this weekend']);
    $apiUser = User::factory()->api()->create();
    resolve(CreateServiceClient::class)($apiUser, 'Nightly enrollment warehouse synchronization client', now()->addYear());

    $paths = [
        ...FilamentPages::in('administration'),
        "/administration/users/{$person->getKey()}",
        "/administration/users/{$person->getKey()}/access",
        "/administration/users/{$apiUser->getKey()}/api-credentials",
        "/administration/roles/{$role->getKey()}",
    ];

    expect($paths)->toAllFitEachWidth();
});
