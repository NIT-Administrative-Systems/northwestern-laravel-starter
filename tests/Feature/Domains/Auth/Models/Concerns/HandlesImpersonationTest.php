<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Models\Concerns;

use App\Domains\Access\Enums\RoleModificationOrigin;
use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Models\Role;
use App\Domains\Auth\Models\Concerns\HandlesImpersonation;
use App\Domains\User\Models\User;
use Mockery;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;

#[CoversTrait(HandlesImpersonation::class)]
final class HandlesImpersonationTest extends TestCase
{
    public function test_someone_with_the_permission_may_impersonate(): void
    {
        $this->bindImpersonateService(false);

        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(SystemPermission::ManageImpersonation);
        $user->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $this->assertTrue($user->canImpersonate());
    }

    public function test_someone_without_the_permission_may_not_impersonate(): void
    {
        $this->bindImpersonateService(false);

        $user = User::factory()->createOne();

        $this->assertFalse($user->canImpersonate());
    }

    public function test_nobody_impersonates_while_already_impersonating(): void
    {
        $this->bindImpersonateService(true);

        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(SystemPermission::ManageImpersonation);
        $user->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $this->assertFalse($user->canImpersonate());
    }

    public function test_nobody_may_impersonate_themselves(): void
    {
        $this->bindImpersonateService(false);

        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(SystemPermission::ManageImpersonation);
        $user->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $this->assertFalse($user->canImpersonateUser($user));
    }

    public function test_nobody_may_impersonate_someone_while_impersonating(): void
    {
        $this->bindImpersonateService(true);

        $user = User::factory()->createOne();
        $target = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(SystemPermission::ManageImpersonation);
        $user->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $this->assertFalse($user->canImpersonateUser($target));
    }

    public function test_an_api_user_cannot_be_impersonated(): void
    {
        $this->bindImpersonateService(false);

        $user = User::factory()->createOne();
        $target = User::factory()->api()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(SystemPermission::ManageImpersonation);
        $user->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $this->assertFalse($user->canImpersonateUser($target));
        $this->assertFalse($target->canBeImpersonated());
    }

    public function test_an_administrator_may_impersonate_an_ordinary_person(): void
    {
        $this->bindImpersonateService(false);

        $user = User::factory()->createOne();
        $target = User::factory()->affiliate()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(SystemPermission::ManageImpersonation);
        $user->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $this->assertTrue($target->canBeImpersonated());
        $this->assertTrue($user->canImpersonateUser($target));
    }

    public function test_a_deleted_person_cannot_be_impersonated(): void
    {
        $this->bindImpersonateService(false);

        $user = User::factory()->createOne();
        $user->givePermissionTo(SystemPermission::ManageImpersonation);
        $target = User::factory()->affiliate()->createOne();
        $target->delete();

        $this->assertFalse($target->canBeImpersonated());
        $this->assertFalse($user->canImpersonateUser($target));
    }

    public function test_only_manage_all_may_impersonate_someone_with_manage_all(): void
    {
        $this->bindImpersonateService(false);

        $impersonator = User::factory()->createOne();
        $impersonatorRole = Role::factory()->createOne();
        $impersonatorRole->givePermissionTo(SystemPermission::ManageImpersonation);
        $impersonator->assignRoleWithAudit($impersonatorRole, RoleModificationOrigin::System);

        $target = User::factory()->createOne();
        $targetRole = Role::factory()->createOne();
        $targetRole->givePermissionTo(SystemPermission::ManageAll);
        $target->assignRoleWithAudit($targetRole, RoleModificationOrigin::System);

        $this->actingAs($impersonator);

        $this->assertFalse($target->canBeImpersonated());
        $this->assertFalse($impersonator->canImpersonateUser($target));
    }

    public function test_manage_all_may_impersonate_someone_with_manage_all(): void
    {
        $this->bindImpersonateService(false);

        $impersonator = User::factory()->createOne();
        $impersonatorRole = Role::factory()->createOne();
        $impersonatorRole->givePermissionTo([SystemPermission::ManageImpersonation, SystemPermission::ManageAll]);
        $impersonator->assignRoleWithAudit($impersonatorRole, RoleModificationOrigin::System);

        $target = User::factory()->createOne();
        $targetRole = Role::factory()->createOne();
        $targetRole->givePermissionTo(SystemPermission::ManageAll);
        $target->assignRoleWithAudit($targetRole, RoleModificationOrigin::System);

        $this->actingAs($impersonator);

        $this->assertTrue($target->canBeImpersonated());
        $this->assertTrue($impersonator->canImpersonateUser($target));
    }

    public function test_someone_without_manage_all_may_be_impersonated(): void
    {
        $this->bindImpersonateService(false);

        $impersonator = User::factory()->createOne();
        $impersonatorRole = Role::factory()->createOne();
        $impersonatorRole->givePermissionTo(SystemPermission::ManageImpersonation);
        $impersonator->assignRoleWithAudit($impersonatorRole, RoleModificationOrigin::System);

        $target = User::factory()->createOne();

        $this->actingAs($impersonator);

        $this->assertTrue($target->canBeImpersonated());
        $this->assertTrue($impersonator->canImpersonateUser($target));
    }

    private function bindImpersonateService(bool $isImpersonating): void
    {
        $impersonate = Mockery::mock();
        $impersonate->shouldReceive('isImpersonating')->andReturn($isImpersonating);
        $impersonate->shouldReceive('getImpersonatorId')->andReturn(null);

        $this->app->instance('impersonate', $impersonate);
    }
}
