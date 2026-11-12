<?php

declare(strict_types=1);

use App\Domains\Access\Enums\RoleTypeEnum;
use App\Domains\Access\Models\Role;
use App\Domains\User\Models\User;

/*
 * The modals that add people to a role and deactivate an account pass axe.
 */

beforeEach(fn () => $this->signInAsSuperAdministrator());

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
