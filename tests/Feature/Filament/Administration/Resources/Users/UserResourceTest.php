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
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversNothing;
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

        $this->get(UserResource::getUrl('view', ['record' => $apiUser], panel: AdministrationPanelProvider::ID))->assertOk();
        $this->get(UserResource::getUrl('view', ['record' => $person], panel: AdministrationPanelProvider::ID))->assertNotFound();
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

    public function test_a_persons_record_splits_into_access_activity_and_api_credentials(): void
    {
        $superAdministrator = User::factory()->affiliate()->create();
        $superAdministrator->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ManageAll);
        $person = User::factory()->create();
        $this->actingAs($superAdministrator);

        $this->get(UserResource::getUrl('view', ['record' => $person], panel: AdministrationPanelProvider::ID))
            ->assertOk()
            ->assertSeeInOrder(['Details', 'Access', 'Activity', 'API Credentials'])
            ->assertDontSee('Role History');

        $this->get(UserResource::getUrl('access', ['record' => $person], panel: AdministrationPanelProvider::ID))
            ->assertOk()
            ->assertSee('Roles')
            ->assertSee('Role History');

        $this->get(UserResource::getUrl('activity', ['record' => $person], panel: AdministrationPanelProvider::ID))
            ->assertOk()
            ->assertSee('Audit Logs')
            ->assertSee('Sign-In Records');

        $this->get(UserResource::getUrl('api-credentials', ['record' => $person], panel: AdministrationPanelProvider::ID))
            ->assertOk()
            ->assertSee('Personal Access Tokens')
            ->assertSee('Connected Applications');
    }

    // An administrator opens an API user's record for its credentials, and it has no activity of its own.
    public function test_an_api_users_record_puts_its_credentials_first_and_has_no_activity_page(): void
    {
        config(['api.enabled' => true]);
        $superAdministrator = User::factory()->affiliate()->create();
        $superAdministrator->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ManageAll);
        $apiUser = User::factory()->api()->create();
        $this->actingAs($superAdministrator);

        $this->get(UserResource::getUrl('view', ['record' => $apiUser], panel: AdministrationPanelProvider::ID))
            ->assertOk()
            ->assertSeeInOrder(['Details', 'API Credentials', 'Access'])
            ->assertDontSee(UserResource::getUrl('activity', ['record' => $apiUser], panel: AdministrationPanelProvider::ID));

        $this->get(UserResource::getUrl('activity', ['record' => $apiUser], panel: AdministrationPanelProvider::ID))->assertForbidden();
        $this->get(UserResource::getUrl('api-credentials', ['record' => $apiUser], panel: AdministrationPanelProvider::ID))
            ->assertOk()
            ->assertSee('Service Clients');
    }
}
