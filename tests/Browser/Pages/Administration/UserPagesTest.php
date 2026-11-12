<?php

declare(strict_types=1);

use App\Domains\User\Models\User;

/*
 * A person's administration pages work and pass axe in each theme,
 * for NetID users and API users, and for a deactivated account.
 */

beforeEach(fn () => $this->signInAsSuperAdministrator());

// Pest calls each closure in the test, after sign-in, so every page gets a record.
it("is healthy on a person's pages", function (string $path) {
    expect(visit($path))->toBeHealthyInEachTheme();
})->with([
    'a NetID user' => fn () => '/administration/users/' . User::factory()->create()->getKey(),
    "a NetID user's access" => fn () => '/administration/users/' . User::factory()->create()->getKey() . '/access',
    "a NetID user's activity" => fn () => '/administration/users/' . User::factory()->create()->getKey() . '/activity',
    "a NetID user's API credentials" => fn () => '/administration/users/' . User::factory()->create()->getKey() . '/api-credentials',
    'an API user' => fn () => '/administration/users/' . User::factory()->api()->create()->getKey(),
    "an API user's credentials" => fn () => '/administration/users/' . User::factory()->api()->create()->getKey() . '/api-credentials',
    'a deactivated user' => fn () => '/administration/users/' . tap(User::factory()->create())->delete()->getKey(),
]);
