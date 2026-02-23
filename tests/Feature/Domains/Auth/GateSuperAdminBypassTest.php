<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth;

use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\Auth\Enums\RoleModificationOriginEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;
use App\Providers\AppServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AppServiceProvider::class)]
class GateSuperAdminBypassTest extends TestCase
{
    public function test_user_with_manage_all_can_pass_any_gate_check(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(PermissionEnum::MANAGE_ALL);
        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM);

        $this->assertTrue($user->can(PermissionEnum::VIEW_USERS));
    }

    public function test_user_with_manage_all_bypasses_policy_checks(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(PermissionEnum::MANAGE_ALL);
        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM);

        $anotherUser = User::factory()->createOne();

        $this->assertTrue($user->can('view', $anotherUser));
    }

    public function test_user_without_manage_all_cannot_pass_unauthorized_gate_check(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM);

        $this->assertFalse($user->can(PermissionEnum::VIEW_USERS));
    }

    public function test_user_without_manage_all_respects_granted_permissions(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(PermissionEnum::VIEW_USERS);
        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM);

        $this->assertTrue($user->can(PermissionEnum::VIEW_USERS));
        $this->assertFalse($user->can(PermissionEnum::EDIT_USERS));
    }
}
