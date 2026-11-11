<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Administration\Resources\Users;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\User\Models\User;
use App\Filament\Administration\Resources\UserLoginRecords\UserLoginRecordResource;
use App\Filament\Administration\Resources\Users\Pages\ListUsers;
use App\Filament\Administration\Resources\Users\Pages\ViewUser;
use App\Filament\Administration\Resources\Users\RelationManagers\AuditsRelationManager;
use App\Filament\Administration\Resources\Users\RelationManagers\LoginRecordsRelationManager;
use App\Filament\Administration\Resources\Users\RelationManagers\RoleActivityRelationManager;
use App\Filament\Administration\Resources\Users\UserResource;
use App\Providers\Filament\AdministrationPanelProvider;
use Filament\Facades\Filament;
use Filament\Tables\Filters\TrashedFilter;
use Livewire\Livewire;
use Northwestern\SysDev\SOA\DirectorySearch;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Feature\Domains\User\Actions\Directory\FindOrUpdateUserFromDirectoryTest;
use Tests\TestCase;

#[CoversNothing]
final class UserResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AdministrationPanelProvider::ID);
    }

    // Managing API access is enough to manage API users, and no one else.
    public function test_someone_who_manages_api_access_without_view_users_sees_only_api_users(): void
    {
        $administrator = User::factory()->affiliate()->create();
        $administrator->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ManageApiAccess);
        $apiUser = User::factory()->api()->create();
        $person = User::factory()->create();
        $this->actingAs($administrator);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$apiUser])
            ->assertCanNotSeeTableRecords([$person]);

        $this->get(UserResource::getUrl('view', ['record' => $apiUser]))->assertOk();
        $this->get(UserResource::getUrl('view', ['record' => $person]))->assertNotFound();
    }

    public function test_view_users_sees_everyone(): void
    {
        $administrator = User::factory()->affiliate()->create();
        $administrator->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ViewUsers);
        $apiUser = User::factory()->api()->create();
        $person = User::factory()->create();
        $this->actingAs($administrator);

        Livewire::test(ListUsers::class)->assertCanSeeTableRecords([$apiUser, $person]);
    }

    // Manage All grants every permission through Gate::before, which a direct permission check skips.
    public function test_manage_all_opens_the_history_tabs_and_sign_in_records(): void
    {
        $superAdministrator = User::factory()->affiliate()->create();
        $superAdministrator->givePermissionTo(SystemPermission::ManageAll);
        $person = User::factory()->create();
        $this->actingAs($superAdministrator);

        $this->assertTrue(AuditsRelationManager::canViewForRecord($person, ViewUser::class));
        $this->assertTrue(RoleActivityRelationManager::canViewForRecord($person, ViewUser::class));
        $this->assertTrue(LoginRecordsRelationManager::canViewForRecord($person, ViewUser::class));
        $this->assertTrue(UserLoginRecordResource::canAccess());

        $this->actingAs(User::factory()->affiliate()->create());

        $this->assertFalse(AuditsRelationManager::canViewForRecord($person, ViewUser::class));
        $this->assertFalse(UserLoginRecordResource::canAccess());
    }

    public function test_an_administrator_deactivates_and_reactivates_someone_from_their_page(): void
    {
        $administrator = User::factory()->affiliate()->create();
        $administrator->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ViewUsers, SystemPermission::EditUsers);
        $person = User::factory()->create();
        $this->actingAs($administrator);

        Livewire::test(ViewUser::class, ['record' => $person->getRouteKey()])
            ->assertActionHidden('reactivateUser')
            ->callAction('deactivateUser')
            ->assertNotified('Account Deactivated');

        $this->assertSoftDeleted($person);

        Livewire::test(ViewUser::class, ['record' => $person->getRouteKey()])
            ->assertActionHidden('deactivateUser')
            ->callAction('reactivateUser')
            ->assertNotified('Account Reactivated');

        $this->assertNotSoftDeleted($person);
    }

    public function test_nobody_deactivates_their_own_account_or_does_it_without_edit_users(): void
    {
        $administrator = User::factory()->affiliate()->create();
        $administrator->givePermissionTo(SystemPermission::ManageAll);
        $viewer = User::factory()->affiliate()->create();
        $viewer->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ViewUsers);
        $person = User::factory()->create();

        $this->actingAs($administrator);
        Livewire::test(ViewUser::class, ['record' => $administrator->getRouteKey()])->assertActionHidden('deactivateUser');

        $this->actingAs($viewer);
        Livewire::test(ViewUser::class, ['record' => $person->getRouteKey()])->assertActionHidden('deactivateUser');
    }

    public function test_deactivated_people_are_found_with_the_trashed_filter(): void
    {
        $administrator = User::factory()->affiliate()->create();
        $administrator->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ViewUsers);
        $person = User::factory()->create();
        $deactivated = User::factory()->create();
        $deactivated->delete();
        $this->actingAs($administrator);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$person])
            ->assertCanNotSeeTableRecords([$deactivated])
            ->filterTable(TrashedFilter::class, false)
            ->assertCanSeeTableRecords([$deactivated])
            ->assertCanNotSeeTableRecords([$person]);
    }

    // Looking someone up never reactivates them; the administrator is sent to their page to do it.
    public function test_adding_a_deactivated_northwestern_user_says_so_and_leaves_them_deactivated(): void
    {
        config(['platform.wildcard_photo_sync' => false, 'nusoa.directorySearch.apiKey' => 'key']);
        $directorySearch = $this->createStub(DirectorySearch::class);
        $directorySearch->method('lookup')->willReturn(FindOrUpdateUserFromDirectoryTest::studentData('gone123'));
        $this->app->instance(DirectorySearch::class, $directorySearch);
        $administrator = User::factory()->affiliate()->create();
        $administrator->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ViewUsers, SystemPermission::CreateUsers);
        $deactivated = User::factory()->create(['username' => 'gone123']);
        $deactivated->delete();
        $this->actingAs($administrator);

        Livewire::test(ListUsers::class)
            ->callAction('create-nu-user', ['netid' => 'gone123'])
            ->assertNotified('Account Deactivated')
            ->assertRedirect(UserResource::getUrl('view', ['record' => $deactivated]));

        $this->assertSoftDeleted($deactivated);
    }

    public function test_a_deleted_person_is_offered_no_code_or_impersonation(): void
    {
        $superAdministrator = User::factory()->create();
        $superAdministrator->givePermissionTo(SystemPermission::ManageAll);
        $person = User::factory()->affiliate()->create();
        $this->actingAs($superAdministrator);

        Livewire::test(ViewUser::class, ['record' => $person->getRouteKey()])
            ->assertActionVisible('sendLoginCode')
            ->assertActionVisible('impersonate');

        $person->delete();

        Livewire::test(ViewUser::class, ['record' => $person->getRouteKey()])
            ->assertActionHidden('sendLoginCode')
            ->assertActionHidden('impersonate');
    }

    public function test_a_deleted_person_cannot_be_impersonated_by_posting_to_the_route(): void
    {
        $superAdministrator = User::factory()->create();
        $superAdministrator->givePermissionTo(SystemPermission::ManageAll);
        $person = User::factory()->affiliate()->create();
        $person->delete();

        $this->actingAs($superAdministrator)->post(route('impersonate', [$person->id]));

        $this->assertFalse(resolve('impersonate')->isImpersonating());
        $this->assertTrue(auth()->user()->is($superAdministrator));
    }
}
