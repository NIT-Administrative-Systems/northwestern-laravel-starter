<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Access\Actions;

use App\Domains\Access\Actions\SyncRolePermissions;
use App\Domains\Access\Enums\RoleTypeEnum;
use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Models\Role;
use App\Domains\Core\Enums\AuditEvent;
use App\Domains\Core\Models\Audit;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SyncRolePermissions::class)]
final class SyncRolePermissionsTest extends TestCase
{
    public function test_an_interface_role_gets_the_interface_permissions_chosen(): void
    {
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();

        $this->sync($role, [SystemPermission::EditUsers, SystemPermission::ViewUsers], $this->editor());

        $this->assertSame([SystemPermission::EditUsers->value], $this->namesOf($role));
        $this->assertTrue(Audit::query()->where('event', AuditEvent::PermissionsModified->value)->where('auditable_id', $role->id)->exists());
    }

    public function test_an_api_role_gets_only_api_permissions(): void
    {
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApiIntegration)->create();

        $this->sync($role, [SystemPermission::ViewUsers, SystemPermission::EditUsers, SystemPermission::ManageAll], $this->editor(SystemPermission::ManageAll));

        $this->assertSame([SystemPermission::ViewUsers->value], $this->namesOf($role));
    }

    public function test_only_an_editor_with_manage_all_grants_system_managed_permissions(): void
    {
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationAdmin)->create();

        $this->sync($role, [SystemPermission::ManageAll, SystemPermission::ViewAuditLogs], $this->editor());
        $this->assertSame([], $this->namesOf($role));

        $this->sync($role, [SystemPermission::ViewAuditLogs], $this->editor(SystemPermission::ManageAll));
        $this->assertSame([SystemPermission::ViewAuditLogs->value], $this->namesOf($role));
    }

    public function test_an_editor_without_manage_all_keeps_the_roles_system_managed_permissions(): void
    {
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->hasPermissions([SystemPermission::ViewAuditLogs, SystemPermission::EditUsers])->create();

        $this->sync($role, [SystemPermission::CreateUsers], $this->editor());

        $this->assertEqualsCanonicalizing([SystemPermission::CreateUsers->value, SystemPermission::ViewAuditLogs->value], $this->namesOf($role));
    }

    public function test_a_role_that_became_an_api_role_drops_its_interface_and_system_permissions(): void
    {
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->hasPermissions([SystemPermission::ViewAuditLogs, SystemPermission::EditUsers])->create();
        $role->load('role_type');
        $role->forceFill(['role_type_id' => Role::factory()->forRoleType(RoleTypeEnum::ApiIntegration)->create()->role_type_id])->save();

        $this->sync($role, [SystemPermission::EditUsers], $this->editor());

        $this->assertSame([], $this->namesOf($role));
    }

    /**
     * @param  list<SystemPermission>  $permissions
     */
    private function sync(Role $role, array $permissions, User $editor): void
    {
        resolve(SyncRolePermissions::class)($role, array_map(fn (SystemPermission $permission): string => $permission->value, $permissions), $editor);
    }

    private function editor(SystemPermission ...$permissions): User
    {
        $editor = User::factory()->create();
        $editor->givePermissionTo(SystemPermission::EditRoles, ...$permissions);

        return $editor;
    }

    /** @return list<string> */
    private function namesOf(Role $role): array
    {
        return $role->fresh()->permissions->pluck('name')->values()->all();
    }
}
