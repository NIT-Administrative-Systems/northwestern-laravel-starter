<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Administration\Resources\Roles\RelationManagers;

use App\Domains\Access\Enums\RoleTypeEnum;
use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Models\Role;
use App\Domains\User\Models\User;
use App\Filament\Administration\Resources\Roles\Pages\ViewRole;
use App\Filament\Administration\Resources\Roles\RelationManagers\UsersRelationManager;
use App\Providers\Filament\AdministrationPanelProvider;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class UsersRelationManagerTest extends TestCase
{
    private User $assigner;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AdministrationPanelProvider::ID);

        $this->assigner = User::factory()->create();
        $this->assigner->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ViewRoles, SystemPermission::ViewUsers, SystemPermission::AssignRoles);
        $this->actingAs($this->assigner);
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

        $this->assertNotContains($this->assigner->id, array_keys($this->search($role, $this->assigner->username)));

        $this->assign($role, $this->assigner)->assertForbidden();

        $this->assertFalse($this->assigner->fresh()->hasRole($role));
    }

    public function test_a_super_administrator_cannot_assign_a_role_to_themselves_either(): void
    {
        $this->assigner->givePermissionTo(SystemPermission::ManageAll);
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();

        $this->assign($role, $this->assigner)->assertForbidden();

        $this->assertFalse($this->assigner->fresh()->hasRole($role));
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
