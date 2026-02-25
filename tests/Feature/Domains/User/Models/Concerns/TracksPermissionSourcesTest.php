<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Models\Concerns;

use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\Auth\Enums\RoleModificationOriginEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\Concerns\TracksPermissionSources;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;

#[CoversTrait(TracksPermissionSources::class)]
class TracksPermissionSourcesTest extends TestCase
{
    private User $user;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->createOne();
        $this->role = Role::factory()->createOne();
    }

    public function test_has_permission_from_role_returns_true_when_user_has_role_with_permission(): void
    {
        $this->role->givePermissionTo(PermissionEnum::VIEW_USERS);
        $this->user->assignRoleWithAudit($this->role, RoleModificationOriginEnum::SYSTEM);

        $this->assertTrue($this->user->hasPermissionFromRole(PermissionEnum::VIEW_USERS, $this->role));
    }

    public function test_has_permission_from_role_returns_false_when_user_has_role_without_permission(): void
    {
        $this->user->assignRoleWithAudit($this->role, RoleModificationOriginEnum::SYSTEM);

        $this->assertFalse($this->user->hasPermissionFromRole(PermissionEnum::VIEW_USERS, $this->role));
    }

    public function test_has_permission_from_role_returns_false_when_user_does_not_have_role(): void
    {
        $this->role->givePermissionTo(PermissionEnum::VIEW_USERS);

        $this->assertFalse($this->user->hasPermissionFromRole(PermissionEnum::VIEW_USERS, $this->role));
    }

    public function test_has_permission_from_role_returns_false_when_permission_comes_from_different_role(): void
    {
        $roleWithPermission = Role::factory()->createOne(['name' => 'role-with-permission']);
        $roleWithPermission->givePermissionTo(PermissionEnum::VIEW_USERS);

        $roleWithoutPermission = Role::factory()->createOne(['name' => 'role-without-permission']);

        $this->user->assignRoleWithAudit([$roleWithPermission, $roleWithoutPermission], RoleModificationOriginEnum::SYSTEM);

        $this->assertTrue($this->user->hasPermissionTo(PermissionEnum::VIEW_USERS));
        $this->assertFalse($this->user->hasPermissionFromRole(PermissionEnum::VIEW_USERS, $roleWithoutPermission));
        $this->assertTrue($this->user->hasPermissionFromRole(PermissionEnum::VIEW_USERS, $roleWithPermission));
    }

    public function test_memoizes_role_permissions_across_multiple_checks(): void
    {
        $this->role->givePermissionTo([
            PermissionEnum::VIEW_USERS,
            PermissionEnum::EDIT_USERS,
        ]);
        $this->user->assignRoleWithAudit($this->role, RoleModificationOriginEnum::SYSTEM);

        $this->assertTrue($this->user->hasPermissionFromRole(PermissionEnum::VIEW_USERS, $this->role));

        $queryCount = 0;
        $this->app['db']->listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $this->assertTrue($this->user->hasPermissionFromRole(PermissionEnum::EDIT_USERS, $this->role));

        $this->assertLessThanOrEqual(1, $queryCount);
    }

    public function test_get_roles_with_permission_returns_all_roles_granting_permission(): void
    {
        $adminRole = Role::factory()->createOne(['name' => 'admin']);
        $adminRole->givePermissionTo(PermissionEnum::VIEW_USERS);

        $managerRole = Role::factory()->createOne(['name' => 'manager']);
        $managerRole->givePermissionTo(PermissionEnum::VIEW_USERS);

        $basicRole = Role::factory()->createOne(['name' => 'basic']);

        $this->user->assignRoleWithAudit([$adminRole, $managerRole, $basicRole], RoleModificationOriginEnum::SYSTEM);

        $roles = $this->user->getRolesWithPermission(PermissionEnum::VIEW_USERS);

        $this->assertCount(2, $roles);
        $this->assertTrue($roles->contains('id', $adminRole->id));
        $this->assertTrue($roles->contains('id', $managerRole->id));
        $this->assertFalse($roles->contains('id', $basicRole->id));
    }

    public function test_get_roles_with_permission_returns_empty_when_no_roles_grant_permission(): void
    {
        $this->user->assignRoleWithAudit($this->role, RoleModificationOriginEnum::SYSTEM);

        $roles = $this->user->getRolesWithPermission(PermissionEnum::VIEW_USERS);

        $this->assertCount(0, $roles);
    }

    public function test_get_roles_with_permission_returns_empty_when_user_has_no_roles(): void
    {
        $roles = $this->user->getRolesWithPermission(PermissionEnum::VIEW_USERS);

        $this->assertCount(0, $roles);
    }

    public function test_get_permissions_from_role_returns_all_permissions_from_role(): void
    {
        $this->role->givePermissionTo([
            PermissionEnum::VIEW_USERS,
            PermissionEnum::EDIT_USERS,
            PermissionEnum::VIEW_ROLES,
        ]);
        $this->user->assignRoleWithAudit($this->role, RoleModificationOriginEnum::SYSTEM);

        $permissions = $this->user->getPermissionsFromRole($this->role);

        $this->assertCount(3, $permissions);
        $this->assertTrue($permissions->contains(PermissionEnum::VIEW_USERS));
        $this->assertTrue($permissions->contains(PermissionEnum::EDIT_USERS));
        $this->assertTrue($permissions->contains(PermissionEnum::VIEW_ROLES));
    }

    public function test_get_permissions_from_role_returns_empty_when_user_does_not_have_role(): void
    {
        $this->role->givePermissionTo(PermissionEnum::VIEW_USERS);

        $permissions = $this->user->getPermissionsFromRole($this->role);

        $this->assertCount(0, $permissions);
    }

    public function test_get_permissions_from_role_returns_empty_when_role_has_no_permissions(): void
    {
        $this->user->assignRoleWithAudit($this->role, RoleModificationOriginEnum::SYSTEM);

        $permissions = $this->user->getPermissionsFromRole($this->role);

        $this->assertCount(0, $permissions);
    }

    public function test_get_permissions_from_role_returns_permission_enum_instances(): void
    {
        $this->role->givePermissionTo(PermissionEnum::VIEW_USERS);
        $this->user->assignRoleWithAudit($this->role, RoleModificationOriginEnum::SYSTEM);

        $permissions = $this->user->getPermissionsFromRole($this->role);

        $this->assertContainsOnlyInstancesOf(PermissionEnum::class, $permissions);
    }
}
