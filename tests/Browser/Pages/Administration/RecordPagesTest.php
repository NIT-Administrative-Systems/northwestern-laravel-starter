<?php

declare(strict_types=1);

use App\Domains\Access\Models\Role;
use App\Domains\Core\Models\Audit;
use App\Domains\Support\Models\Announcement;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\User\Models\User;

/*
 * Administration pages that need a record work and pass axe in each theme.
 *
 * A person's pages are in UserPagesTest. Add your own record pages
 * to the dataset, each with a closure that makes the record and
 * then returns the path to the page that shows this record.
 */

beforeEach(fn () => $this->signInAsSuperAdministrator());

// Pest calls each closure in the test, after sign-in, so every page gets a record.
it('is healthy on record pages', function (string $path) {
    expect(visit($path))->toBeHealthyInEachTheme();
})->with([
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

    // The @pierre/diffs viewer renders syntax colors on its change backgrounds
    // inside a shadow DOM, and none of its themes meet color contrast there.
    expect(visit("/administration/audits/{$audit->getKey()}"))->toBeHealthyInEachTheme(exclude: ['diffs-container']);
});
