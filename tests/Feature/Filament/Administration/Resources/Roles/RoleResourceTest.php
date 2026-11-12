<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Administration\Resources\Roles;

use App\Domains\Access\Enums\RoleModificationOrigin;
use App\Domains\Access\Enums\RoleTypeEnum;
use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\RoleType;
use App\Domains\User\Models\User;
use App\Filament\Administration\Resources\Roles\Pages\CreateRole;
use App\Filament\Administration\Resources\Roles\Pages\EditRole;
use App\Filament\Administration\Resources\Roles\RoleResource;
use App\Providers\Filament\AdministrationPanelProvider;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class RoleResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AdministrationPanelProvider::ID);
    }

    public function test_creating_a_role_grants_the_chosen_permissions(): void
    {
        $this->actingAs($this->roleEditor());

        Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'Reviewer', 'role_type_id' => $this->roleType(RoleTypeEnum::ApplicationRole)->id])
            ->fillForm(['regular_permissions' => [SystemPermission::EditUsers->value]])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame([SystemPermission::EditUsers->value], $this->permissionsOf('Reviewer'));
    }

    public function test_an_editor_without_manage_all_cannot_grant_a_system_permission_through_a_hidden_list(): void
    {
        $this->actingAs($this->roleEditor());

        Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'Escalated', 'role_type_id' => $this->roleType(RoleTypeEnum::ApplicationRole)->id])
            // Hidden for this role type and without Manage All, so it's never validated.
            ->set('data.api_permissions', [SystemPermission::ManageAll->value])
            ->set('data.system_permissions', [SystemPermission::ManageAll->value])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame([], $this->permissionsOf('Escalated'));
    }

    public function test_an_editor_without_manage_all_cannot_grant_a_system_permission_when_editing(): void
    {
        $editor = $this->roleEditor();
        $this->actingAs($editor);

        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();
        $editor->assignRoleWithAudit($role, RoleModificationOrigin::System);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->set('data.api_permissions', [SystemPermission::ManageAll->value])
            ->set('data.system_permissions', [SystemPermission::ManageAll->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($role->fresh()->hasPermissionTo(SystemPermission::ManageAll));
        $this->assertFalse($editor->fresh()->hasPermissionTo(SystemPermission::ManageAll));
    }

    public function test_an_editor_without_manage_all_keeps_a_roles_system_permissions(): void
    {
        $this->actingAs($this->roleEditor());

        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->hasPermissions([SystemPermission::ViewAuditLogs, SystemPermission::EditUsers])->create();

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['regular_permissions' => [SystemPermission::CreateUsers->value]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(
            [SystemPermission::CreateUsers->value, SystemPermission::ViewAuditLogs->value],
            $this->permissionsOf($role->name),
        );
    }

    public function test_an_editor_with_manage_all_grants_system_permissions(): void
    {
        $this->actingAs($this->roleEditor(SystemPermission::ManageAll));

        Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'Auditor', 'role_type_id' => $this->roleType(RoleTypeEnum::ApplicationRole)->id])
            ->fillForm(['system_permissions' => [SystemPermission::ViewAuditLogs->value]])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame([SystemPermission::ViewAuditLogs->value], $this->permissionsOf('Auditor'));
    }

    public function test_switching_a_new_role_to_api_integration_drops_the_hidden_interface_permissions(): void
    {
        $this->actingAs($this->roleEditor());

        Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'Switched', 'role_type_id' => $this->roleType(RoleTypeEnum::ApplicationRole)->id])
            ->fillForm(['regular_permissions' => [SystemPermission::EditUsers->value]])
            ->fillForm(['role_type_id' => $this->roleType(RoleTypeEnum::ApiIntegration)->id])
            ->fillForm(['api_permissions' => [SystemPermission::ViewUsers->value]])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame([SystemPermission::ViewUsers->value], $this->permissionsOf('Switched'));
    }

    public function test_changing_a_roles_type_drops_the_permissions_the_new_type_hides(): void
    {
        $this->actingAs($this->roleEditor());

        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->hasPermissions([SystemPermission::EditUsers])->create();

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['role_type_id' => $this->roleType(RoleTypeEnum::ApiIntegration)->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([], $this->permissionsOf($role->name));
        $this->assertSame(RoleTypeEnum::ApiIntegration, $role->fresh()->role_type->slug);
    }

    public function test_a_global_search_result_counts_the_roles_people_and_permissions(): void
    {
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->hasPermissions([SystemPermission::ViewUsers, SystemPermission::EditUsers])->create();
        User::factory()->count(3)->affiliate()->create()->each->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $result = RoleResource::getGlobalSearchEloquentQuery()->whereKey($role)->sole();

        $this->assertSame([
            'Type' => RoleTypeEnum::ApplicationRole->getLabel(),
            'Users' => '3 assigned',
            'Permissions' => '2 permissions',
        ], RoleResource::getGlobalSearchResultDetails($result));
    }

    private function roleEditor(SystemPermission ...$permissions): User
    {
        $editor = User::factory()->create();
        $editor->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ViewRoles, SystemPermission::EditRoles, ...$permissions);

        return $editor;
    }

    private function roleType(RoleTypeEnum $type): RoleType
    {
        return RoleType::query()->where('slug', $type)->sole();
    }

    /** @return list<string> */
    private function permissionsOf(string $roleName): array
    {
        return Role::query()->where('name', $roleName)->sole()->permissions->pluck('name')->values()->all();
    }
}
