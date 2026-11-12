<?php

declare(strict_types=1);

use App\Domains\Access\Enums\RoleTypeEnum;
use App\Domains\Access\Models\Role;
use App\Domains\Api\Actions\ServiceClients\CreateServiceClient;
use App\Domains\Support\Models\Announcement;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginRecord;
use Northwestern\SysDev\Chassis\Testing\Browser\FilamentPages;

/*
 * Every administration page fits each width, with values long enough to wrap.
 *
 * Add a record page's path to the list, and give its record long
 * values, since those are what break a layout in practice.
 */

beforeEach(fn () => $this->signInAsSuperAdministrator());

it('fits every administration page and its tables at each width', function () {
    auth()->user()->forceFill(['username' => 'featherstonehaugh.max'])->save();
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
