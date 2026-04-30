<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth;

use App\Domains\Auth\Enums\RoleModificationOrigin;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;
use Tests\TestCase;

final class GateSuperAdminBypassTest extends TestCase
{
    public function test_user_with_manage_all_can_pass_any_gate_check(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(SystemPermission::ManageAll);
        $user->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $this->assertTrue($user->can(SystemPermission::ViewUsers));
    }

    public function test_user_with_manage_all_bypasses_policy_checks(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(SystemPermission::ManageAll);
        $user->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $anotherUser = User::factory()->createOne();

        $this->assertTrue($user->can('view', $anotherUser));
    }

    public function test_user_without_manage_all_cannot_pass_unauthorized_gate_check(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $user->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $this->assertFalse($user->can(SystemPermission::ViewUsers));
    }

    public function test_user_without_manage_all_respects_granted_permissions(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(SystemPermission::ViewUsers);
        $user->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $this->assertTrue($user->can(SystemPermission::ViewUsers));
        $this->assertFalse($user->can(SystemPermission::EditUsers));
    }
}
