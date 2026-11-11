<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Administration\Resources\Roles\RelationManagers;

use App\Domains\Access\Enums\RoleModificationOrigin;
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
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Northwestern\SysDev\SOA\DirectorySearch;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Feature\Filament\Administration\Concerns\AssertsLinkedRows;
use Tests\TestCase;

#[CoversNothing]
final class UsersRelationManagerTest extends TestCase
{
    use AssertsLinkedRows;

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
            ->assertNotified(Notification::make()->success()->title('Adding People')->body('Adding two people to the Editors role. You\'ll get a notification when it\'s done.'));

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

    public function test_a_role_is_assigned_to_someone_else(): void
    {
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();
        $person = User::factory()->create();

        $this->assign($role, $person)->assertHasNoTableActionErrors();

        $this->assertTrue($person->fresh()->hasRole($role));
    }

    public function test_an_interface_role_cannot_be_assigned_to_an_api_user(): void
    {
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationAdmin)->create();
        $apiUser = User::factory()->api()->create();

        // The search never offers an API user for this role, so the ID is sent by hand.
        $this->assign($role, $apiUser)->assertNotified('User Not Assigned');

        $this->assertFalse($apiUser->fresh()->hasRole($role));
    }

    public function test_an_api_integration_role_cannot_be_assigned_to_a_person(): void
    {
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApiIntegration)->create();
        $person = User::factory()->create();

        $this->assign($role, $person)->assertNotified('User Not Assigned');

        $this->assertFalse($person->fresh()->hasRole($role));
    }

    public function test_you_cannot_assign_a_role_to_yourself(): void
    {
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();

        $this->assertNotContains($this->administrator->id, array_keys($this->search($role, $this->administrator->username)));

        $this->assign($role, $this->administrator)->assertForbidden();

        $this->assertFalse($this->administrator->fresh()->hasRole($role));
    }

    public function test_a_super_administrator_cannot_assign_a_role_to_themselves_either(): void
    {
        $this->administrator->givePermissionTo(SystemPermission::ManageAll);
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();

        $this->assign($role, $this->administrator)->assertForbidden();

        $this->assertFalse($this->administrator->fresh()->hasRole($role));
    }

    public function test_a_person_with_no_details_on_file_has_no_empty_links_on_the_tab(): void
    {
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();
        $person = User::factory()->affiliate()->create([
            'first_name' => null,
            'last_name' => null,
            'email' => null,
            'phone' => null,
            'employee_id' => null,
            'hr_employee_id' => null,
            'primary_affiliation' => null,
            'netid_inactive' => null,
            'last_directory_sync_at' => null,
            'directory_sync_last_failed_at' => null,
        ]);
        $person->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $this->assertEveryEmptyCellHasPlaceholder($this->usersOf($role), $person);
    }

    /** @return Testable<UsersRelationManager> */
    private function usersOf(Role $role): Testable
    {
        return Livewire::test(UsersRelationManager::class, ['ownerRecord' => $role, 'pageClass' => ViewRole::class]);
    }

    /**
     * @return Testable<UsersRelationManager>
     */
    private function assign(Role $role, User $user): Testable
    {
        return Livewire::test(UsersRelationManager::class, ['ownerRecord' => $role, 'pageClass' => ViewRole::class])
            ->mountTableAction('attach')
            ->setTableActionData(['recordId' => $user->id])
            ->callMountedTableAction();
    }

    /**
     * @return array<int|string, string>
     */
    private function search(Role $role, string $search): array
    {
        $component = Livewire::test(UsersRelationManager::class, ['ownerRecord' => $role, 'pageClass' => ViewRole::class])
            ->mountTableAction('attach');

        $this->assertInstanceOf(UsersRelationManager::class, $relationManager = $component->instance());
        $this->assertInstanceOf(Select::class, $select = $relationManager->getMountedTableActionForm()?->getComponent('recordId'));

        return $select->getSearchResults($search);
    }
}
