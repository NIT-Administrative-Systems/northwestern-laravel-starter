<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Administration\Resources\Users;

use App\Domains\Auth\Enums\SystemPermission;
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
}
