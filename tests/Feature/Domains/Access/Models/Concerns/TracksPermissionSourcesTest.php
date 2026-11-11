<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Access\Models\Concerns;

use App\Domains\Access\Enums\RoleModificationOrigin;
use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Models\Concerns\TracksPermissionSources;
use App\Domains\Access\Models\Role;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;

#[CoversTrait(TracksPermissionSources::class)]
final class TracksPermissionSourcesTest extends TestCase
{
    private User $user;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->createOne();
        $this->role = Role::factory()->createOne();
    }

    public function test_a_role_the_user_holds_grants_its_permission(): void
    {
        $this->role->givePermissionTo(SystemPermission::ViewUsers);
        $this->user->assignRoleWithAudit($this->role, RoleModificationOrigin::System);

        $this->assertTrue($this->user->hasPermissionFromRole(SystemPermission::ViewUsers, $this->role));
    }

    public function test_a_role_without_the_permission_grants_nothing(): void
    {
        $this->user->assignRoleWithAudit($this->role, RoleModificationOrigin::System);

        $this->assertFalse($this->user->hasPermissionFromRole(SystemPermission::ViewUsers, $this->role));
    }

    public function test_a_role_the_user_does_not_hold_grants_nothing(): void
    {
        $this->role->givePermissionTo(SystemPermission::ViewUsers);

        $this->assertFalse($this->user->hasPermissionFromRole(SystemPermission::ViewUsers, $this->role));
    }

    public function test_only_the_role_that_grants_a_permission_is_its_source(): void
    {
        $roleWithPermission = Role::factory()->createOne(['name' => 'role-with-permission']);
        $roleWithPermission->givePermissionTo(SystemPermission::ViewUsers);

        $roleWithoutPermission = Role::factory()->createOne(['name' => 'role-without-permission']);

        $this->user->assignRoleWithAudit([$roleWithPermission, $roleWithoutPermission], RoleModificationOrigin::System);

        $this->assertTrue($this->user->hasPermissionTo(SystemPermission::ViewUsers));
        $this->assertFalse($this->user->hasPermissionFromRole(SystemPermission::ViewUsers, $roleWithoutPermission));
        $this->assertTrue($this->user->hasPermissionFromRole(SystemPermission::ViewUsers, $roleWithPermission));
    }

    public function test_a_roles_permissions_are_loaded_once_per_user(): void
    {
        $this->role->givePermissionTo([
            SystemPermission::ViewUsers,
            SystemPermission::EditUsers,
        ]);
        $this->user->assignRoleWithAudit($this->role, RoleModificationOrigin::System);

        $this->assertTrue($this->user->hasPermissionFromRole(SystemPermission::ViewUsers, $this->role));

        $queryCount = 0;
        $this->app['db']->listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $this->assertTrue($this->user->hasPermissionFromRole(SystemPermission::EditUsers, $this->role));

        $this->assertLessThanOrEqual(1, $queryCount);
    }

    public function test_every_role_that_grants_a_permission_is_found(): void
    {
        $adminRole = Role::factory()->createOne(['name' => 'admin']);
        $adminRole->givePermissionTo(SystemPermission::ViewUsers);

        $managerRole = Role::factory()->createOne(['name' => 'manager']);
        $managerRole->givePermissionTo(SystemPermission::ViewUsers);

        $basicRole = Role::factory()->createOne(['name' => 'basic']);

        $this->user->assignRoleWithAudit([$adminRole, $managerRole, $basicRole], RoleModificationOrigin::System);

        $roles = $this->user->getRolesWithPermission(SystemPermission::ViewUsers);

        $this->assertCount(2, $roles);
        $this->assertTrue($roles->contains('id', $adminRole->id));
        $this->assertTrue($roles->contains('id', $managerRole->id));
        $this->assertFalse($roles->contains('id', $basicRole->id));
    }

    public function test_no_role_is_found_when_none_grants_the_permission(): void
    {
        $this->user->assignRoleWithAudit($this->role, RoleModificationOrigin::System);

        $roles = $this->user->getRolesWithPermission(SystemPermission::ViewUsers);

        $this->assertCount(0, $roles);
    }

    public function test_a_user_without_roles_has_no_source_for_a_permission(): void
    {
        $roles = $this->user->getRolesWithPermission(SystemPermission::ViewUsers);

        $this->assertCount(0, $roles);
    }

    public function test_a_held_role_gives_all_its_permissions(): void
    {
        $this->role->givePermissionTo([
            SystemPermission::ViewUsers,
            SystemPermission::EditUsers,
            SystemPermission::ViewRoles,
        ]);
        $this->user->assignRoleWithAudit($this->role, RoleModificationOrigin::System);

        $permissions = $this->user->getPermissionsFromRole($this->role);

        $this->assertCount(3, $permissions);
        $this->assertTrue($permissions->contains(SystemPermission::ViewUsers));
        $this->assertTrue($permissions->contains(SystemPermission::EditUsers));
        $this->assertTrue($permissions->contains(SystemPermission::ViewRoles));
    }

    public function test_a_role_the_user_does_not_hold_gives_no_permissions(): void
    {
        $this->role->givePermissionTo(SystemPermission::ViewUsers);

        $permissions = $this->user->getPermissionsFromRole($this->role);

        $this->assertCount(0, $permissions);
    }

    public function test_a_role_without_permissions_gives_none(): void
    {
        $this->user->assignRoleWithAudit($this->role, RoleModificationOrigin::System);

        $permissions = $this->user->getPermissionsFromRole($this->role);

        $this->assertCount(0, $permissions);
    }

    public function test_permissions_from_a_role_are_system_permissions(): void
    {
        $this->role->givePermissionTo(SystemPermission::ViewUsers);
        $this->user->assignRoleWithAudit($this->role, RoleModificationOrigin::System);

        $permissions = $this->user->getPermissionsFromRole($this->role);

        $this->assertContainsOnlyInstancesOf(SystemPermission::class, $permissions);
    }
}
