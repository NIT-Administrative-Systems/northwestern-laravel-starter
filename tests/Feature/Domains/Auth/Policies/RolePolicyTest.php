<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Policies;

use App\Domains\Auth\Enums\RoleTypeEnum;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\Role;
use App\Domains\Auth\Policies\RolePolicy;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RolePolicy::class)]
class RolePolicyTest extends TestCase
{
    public function test_view_any_denies_user_without_permission(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->policy()->viewAny($user));
    }

    public function test_view_any_allows_user_with_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::ViewRoles);

        $this->assertTrue($this->policy()->viewAny($user));
    }

    public function test_create_denies_user_without_permission(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->policy()->create($user));
    }

    public function test_create_allows_user_with_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::EditRoles);

        $this->assertTrue($this->policy()->create($user));
    }

    public function test_update_denies_user_without_permission(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();

        $this->assertFalse($this->policy()->update($user, $role));
    }

    public function test_update_allows_user_with_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::EditRoles);
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();

        $this->assertTrue($this->policy()->update($user, $role));
    }

    public function test_update_denies_system_managed_role(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::EditRoles);
        $role = Role::factory()->systemManaged()->create();

        $this->assertFalse($this->policy()->update($user, $role));
    }

    public function test_delete_denies_user_without_permission(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();

        $this->assertFalse($this->policy()->delete($user, $role));
    }

    public function test_delete_allows_user_with_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::DeleteRoles);
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();

        $this->assertTrue($this->policy()->delete($user, $role));
    }

    public function test_delete_denies_system_managed_role(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::DeleteRoles);
        $role = Role::factory()->systemManaged()->create();

        $this->assertFalse($this->policy()->delete($user, $role));
    }

    public function test_attach_user_denies_without_permission(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();

        $this->assertFalse($this->policy()->attachUser($user, $role));
    }

    public function test_attach_user_allows_with_assign_roles_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::AssignRoles);
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();

        $this->assertTrue($this->policy()->attachUser($user, $role));
    }

    public function test_attach_user_denies_system_managed_without_manage_all(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::AssignRoles);
        $role = Role::factory()->systemManaged()->create();

        $this->assertFalse($this->policy()->attachUser($user, $role));
    }

    public function test_attach_user_denies_system_managed_even_with_manage_all(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::ManageAll);
        $role = Role::factory()->systemManaged()->create();

        // Policy returns false; super admins gain access via Gate::before(), not the policy
        $this->assertFalse($this->policy()->attachUser($user, $role));
    }

    public function test_detach_user_mirrors_attach_user(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::AssignRoles);
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();

        $this->assertTrue($this->policy()->detachUser($user, $role));
    }

    public function test_detach_user_denies_without_permission(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();

        $this->assertFalse($this->policy()->detachUser($user, $role));
    }

    protected function policy(): RolePolicy
    {
        return resolve(RolePolicy::class);
    }
}
