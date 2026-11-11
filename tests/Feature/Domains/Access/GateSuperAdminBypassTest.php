<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Access;

use App\Domains\Access\Enums\RoleModificationOrigin;
use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Models\Role;
use App\Domains\User\Models\User;
use Tests\TestCase;

final class GateSuperAdminBypassTest extends TestCase
{
    public function test_manage_all_passes_any_gate(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(SystemPermission::ManageAll);
        $user->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $this->assertTrue($user->can(SystemPermission::ViewUsers));
    }

    public function test_manage_all_passes_any_policy(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(SystemPermission::ManageAll);
        $user->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $anotherUser = User::factory()->createOne();

        $this->assertTrue($user->can('view', $anotherUser));
    }

    public function test_others_are_refused_a_gate_they_lack(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $user->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $this->assertFalse($user->can(SystemPermission::ViewUsers));
    }

    public function test_others_pass_a_gate_their_permissions_allow(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(SystemPermission::ViewUsers);
        $user->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $this->assertTrue($user->can(SystemPermission::ViewUsers));
        $this->assertFalse($user->can(SystemPermission::EditUsers));
    }
}
