<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Models;

use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\Auth\Enums\RoleModificationOriginEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;
use Filament\Panel;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class UserRoleAccessTest extends TestCase
{
    public function test_assign_role_is_not_publicly_callable(): void
    {
        $reflection = new ReflectionMethod(User::class, 'assignRole');

        $this->assertTrue($reflection->isPrivate());
    }

    public function test_remove_role_is_not_publicly_callable(): void
    {
        $reflection = new ReflectionMethod(User::class, 'removeRole');

        $this->assertTrue($reflection->isPrivate());
    }

    public function test_sync_roles_is_not_publicly_callable(): void
    {
        $reflection = new ReflectionMethod(User::class, 'syncRoles');

        $this->assertTrue($reflection->isPrivate());
    }

    public function test_assign_role_with_audit_is_publicly_callable(): void
    {
        $reflection = new ReflectionMethod(User::class, 'assignRoleWithAudit');

        $this->assertTrue($reflection->isPublic());
    }

    public function test_remove_role_with_audit_is_publicly_callable(): void
    {
        $reflection = new ReflectionMethod(User::class, 'removeRoleWithAudit');

        $this->assertTrue($reflection->isPublic());
    }

    public function test_can_access_administration_panel_with_permission(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(PermissionEnum::ACCESS_ADMINISTRATION_PANEL);
        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM);

        $panel = Mockery::mock(Panel::class);
        $panel->expects('getId')->andReturn('administration');

        $this->assertTrue($user->canAccessPanel($panel));
    }

    public function test_cannot_access_administration_panel_without_permission(): void
    {
        $user = User::factory()->createOne();

        $panel = Mockery::mock(Panel::class);
        $panel->expects('getId')->andReturn('administration');

        $this->assertFalse($user->canAccessPanel($panel));
    }
}
