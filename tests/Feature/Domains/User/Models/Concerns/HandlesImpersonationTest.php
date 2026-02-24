<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Models\Concerns;

use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\Auth\Enums\RoleModificationOriginEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\Concerns\HandlesImpersonation;
use App\Domains\User\Models\User;
use Mockery;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;

#[CoversTrait(HandlesImpersonation::class)]
class HandlesImpersonationTest extends TestCase
{
    public function test_can_impersonate_returns_true_when_user_has_permission_and_not_impersonated(): void
    {
        $this->bindImpersonateService(false);

        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(PermissionEnum::MANAGE_IMPERSONATION);
        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM);

        $this->assertTrue($user->canImpersonate());
    }

    public function test_can_impersonate_returns_false_when_user_lacks_permission(): void
    {
        $this->bindImpersonateService(false);

        $user = User::factory()->createOne();

        $this->assertFalse($user->canImpersonate());
    }

    public function test_can_impersonate_returns_false_when_currently_impersonated(): void
    {
        $this->bindImpersonateService(true);

        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(PermissionEnum::MANAGE_IMPERSONATION);
        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM);

        $this->assertFalse($user->canImpersonate());
    }

    public function test_can_impersonate_user_returns_false_for_self_target(): void
    {
        $this->bindImpersonateService(false);

        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(PermissionEnum::MANAGE_IMPERSONATION);
        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM);

        $this->assertFalse($user->canImpersonateUser($user));
    }

    public function test_can_impersonate_user_returns_false_when_already_impersonating(): void
    {
        $this->bindImpersonateService(true);

        $user = User::factory()->createOne();
        $target = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(PermissionEnum::MANAGE_IMPERSONATION);
        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM);

        $this->assertFalse($user->canImpersonateUser($target));
    }

    public function test_can_impersonate_user_returns_false_when_target_is_api_user(): void
    {
        $this->bindImpersonateService(false);

        $user = User::factory()->createOne();
        $target = User::factory()->api()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(PermissionEnum::MANAGE_IMPERSONATION);
        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM);

        $this->assertFalse($user->canImpersonateUser($target));
        $this->assertFalse($target->canBeImpersonated());
    }

    public function test_can_impersonate_user_returns_true_when_all_guard_conditions_pass(): void
    {
        $this->bindImpersonateService(false);

        $user = User::factory()->createOne();
        $target = User::factory()->affiliate()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(PermissionEnum::MANAGE_IMPERSONATION);
        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM);

        $this->assertTrue($target->canBeImpersonated());
        $this->assertTrue($user->canImpersonateUser($target));
    }

    private function bindImpersonateService(bool $isImpersonating): void
    {
        $impersonate = Mockery::mock();
        $impersonate->shouldReceive('isImpersonating')->andReturn($isImpersonating);
        $impersonate->shouldReceive('getImpersonatorId')->andReturn(null);

        $this->app->instance('impersonate', $impersonate);
    }
}
