<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Administration\Resources\Users\RelationManagers;

use App\Domains\Access\Enums\RoleTypeEnum;
use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Models\Role;
use App\Domains\User\Models\User;
use App\Filament\Administration\Resources\Users\Pages\ViewUser;
use App\Filament\Administration\Resources\Users\RelationManagers\RolesRelationManager;
use App\Providers\Filament\AdministrationPanelProvider;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class RolesRelationManagerTest extends TestCase
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

        $this->relationManager($person)->assertTableActionVisible('attach');
        $this->assign($person, $role)->assertHasNoTableActionErrors();

        $this->assertTrue($person->fresh()->hasRole($role));
    }

    public function test_an_api_integration_role_cannot_be_assigned_to_a_person(): void
    {
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApiIntegration)->create();
        // A role that suits them, so the action is offered.
        Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();
        $person = User::factory()->create();

        // Only roles that suit the person are offered, and the choice must be one of them.
        $this->assign($person, $role)->assertHasTableActionErrors(['recordId']);

        $this->assertFalse($person->fresh()->hasRole($role));
    }

    public function test_an_interface_role_cannot_be_assigned_to_an_api_user(): void
    {
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();
        Role::factory()->forRoleType(RoleTypeEnum::ApiIntegration)->create();
        $apiUser = User::factory()->api()->create();

        $this->assign($apiUser, $role)->assertHasTableActionErrors(['recordId']);

        $this->assertFalse($apiUser->fresh()->hasRole($role));
    }

    public function test_you_cannot_assign_a_role_to_yourself(): void
    {
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();

        $this->relationManager($this->assigner)->assertTableActionHidden('attach');

        $this->assertFalse($this->assigner->fresh()->hasRole($role));
    }

    /**
     * @return Testable<RolesRelationManager>
     */
    private function relationManager(User $user): Testable
    {
        return Livewire::test(RolesRelationManager::class, ['ownerRecord' => $user, 'pageClass' => ViewUser::class]);
    }

    /**
     * @return Testable<RolesRelationManager>
     */
    private function assign(User $user, Role $role): Testable
    {
        return $this->relationManager($user)
            ->mountTableAction('attach')
            ->setTableActionData(['recordId' => $role->id])
            ->callMountedTableAction();
    }
}
