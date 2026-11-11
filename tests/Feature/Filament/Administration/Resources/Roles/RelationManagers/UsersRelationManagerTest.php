<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Administration\Resources\Roles\RelationManagers;

use App\Domains\Access\Enums\RoleTypeEnum;
use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Models\Role;
use App\Domains\User\Models\User;
use App\Filament\Administration\Resources\Roles\Pages\ViewRole;
use App\Filament\Administration\Resources\Roles\RelationManagers\UsersRelationManager;
use App\Filament\Administration\Resources\Users\Pages\ListUsers;
use App\Providers\Filament\AdministrationPanelProvider;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Northwestern\SysDev\SOA\DirectorySearch;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class UsersRelationManagerTest extends TestCase
{
    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AdministrationPanelProvider::ID);
        config(['platform.wildcard_photo_sync' => false, 'local-auth.enabled' => true]);
        $directorySearch = $this->createStub(DirectorySearch::class);
        $directorySearch->method('lookup')->willReturn(false);
        $this->app->instance(DirectorySearch::class, $directorySearch);

        $this->administrator = User::factory()->affiliate()->create();
        $this->administrator->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ViewRoles, SystemPermission::ViewUsers, SystemPermission::AssignRoles, SystemPermission::CreateUsers);
        $this->actingAs($this->administrator);
    }

    public function test_people_pasted_on_a_roles_tab_are_given_that_role(): void
    {
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create(['name' => 'Editors']);

        $this->usersOf($role)
            ->assertActionVisible(TestAction::make('importPeople')->table())
            ->callAction(TestAction::make('addPeople')->table(), ['people' => "guest@example.com\nother@example.com"])
            ->assertHasNoFormErrors()
            ->assertNotified('Adding People');

        $this->assertSame(2, $role->users()->count());
    }

    public function test_people_in_an_uploaded_csv_are_given_the_role(): void
    {
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create(['name' => 'Editors']);
        $csv = UploadedFile::fake()->createWithContent('people.csv', "Email,First Name,Last Name\nguest@example.com,Willie,Wildcat\n");

        $this->usersOf($role)
            ->callAction(TestAction::make('importPeople')->table(), ['file' => $csv])
            ->assertHasNoFormErrors();

        $this->assertSame('Willie', $role->users()->sole()->first_name);
    }

    public function test_more_than_the_limit_is_refused_in_the_form(): void
    {
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();

        $this->usersOf($role)
            ->callAction(TestAction::make('addPeople')->table(), ['people' => implode(' ', range(0, 1000))])
            ->assertHasFormErrors(['people']);
    }

    public function test_people_are_not_added_in_bulk_to_locked_or_api_integration_roles(): void
    {
        foreach ([
            Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->assignmentLocked()->create(),
            Role::factory()->forRoleType(RoleTypeEnum::ApiIntegration)->create(),
        ] as $role) {
            $this->usersOf($role)
                ->assertActionHidden(TestAction::make('addPeople')->table())
                ->assertActionHidden(TestAction::make('importPeople')->table());
        }
    }

    public function test_the_users_list_adds_people_to_the_role_chosen(): void
    {
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create(['name' => 'Editors']);

        Livewire::test(ListUsers::class)
            ->assertActionVisible('importPeople')
            ->callAction('addPeople', ['role_id' => $role->getKey(), 'people' => 'guest@example.com'])
            ->assertHasNoFormErrors()
            ->assertNotified('Adding People');

        $this->assertSame(1, $role->users()->count());
    }

    public function test_the_users_list_offers_it_only_to_someone_who_may_create_users_and_assign_a_role(): void
    {
        Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();
        $this->administrator->revokePermissionTo(SystemPermission::CreateUsers);

        Livewire::test(ListUsers::class)
            ->assertActionHidden('addPeople')
            ->assertActionHidden('importPeople');
    }

    /** @return Testable<UsersRelationManager> */
    private function usersOf(Role $role): Testable
    {
        return Livewire::test(UsersRelationManager::class, ['ownerRecord' => $role, 'pageClass' => ViewRole::class]);
    }
}
