<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Models\Concerns;

use App\Domains\Auth\Enums\RoleModificationOriginEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\Audit;
use App\Domains\User\Models\Concerns\AuditsRoles;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;

#[CoversTrait(AuditsRoles::class)]
class AuditsRolesTest extends TestCase
{
    public function test_assign_role_with_audit_creates_audit_record(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();

        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM);

        $audit = Audit::where('event', 'role_assigned')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('role_assigned', $audit->event);
    }

    public function test_assign_role_with_audit_captures_roles_before_and_after(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();

        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM);

        $audit = Audit::where('event', 'role_assigned')
            ->where('auditable_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertNotNull($audit->old_values);
        $this->assertNotNull($audit->new_values);

        $this->assertArrayHasKey('roles_before_change', $audit->old_values);

        $this->assertArrayHasKey('assigned_roles', $audit->new_values);
        $this->assertArrayHasKey('roles_after_change', $audit->new_values);

        /** @var list<array<string, mixed>> $assignedRoles */
        $assignedRoles = $audit->new_values['assigned_roles'];
        $assignedRoleNames = collect($assignedRoles)->pluck('name')->all();
        $this->assertContains($role->name, $assignedRoleNames);

        /** @var list<array<string, mixed>> $afterRoles */
        $afterRoles = $audit->new_values['roles_after_change'];
        $afterRoleNames = collect($afterRoles)->pluck('name')->all();
        $this->assertContains($role->name, $afterRoleNames);
    }

    public function test_remove_role_with_audit_creates_audit_record(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM);

        $user->removeRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM);

        $audit = Audit::where('event', 'role_removed')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('role_removed', $audit->event);
    }

    public function test_remove_role_with_audit_captures_correct_diff(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM);

        $user->removeRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM);

        $audit = Audit::where('event', 'role_removed')
            ->where('auditable_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertNotNull($audit->old_values);
        $this->assertNotNull($audit->new_values);

        /** @var list<array<string, mixed>> $beforeRoles */
        $beforeRoles = $audit->old_values['roles_before_change'];
        $beforeRoleNames = collect($beforeRoles)->pluck('name')->all();
        $this->assertContains($role->name, $beforeRoleNames);

        /** @var list<array<string, mixed>> $removedRoles */
        $removedRoles = $audit->new_values['removed_roles'];
        $removedRoleNames = collect($removedRoles)->pluck('name')->all();
        $this->assertContains($role->name, $removedRoleNames);

        /** @var list<array<string, mixed>> $afterRoles */
        $afterRoles = $audit->new_values['roles_after_change'];
        $afterRoleNames = collect($afterRoles)->pluck('name')->all();
        $this->assertNotContains($role->name, $afterRoleNames);
    }

    public function test_audit_includes_modification_origin(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();

        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::UI_ACTION);

        $audit = Audit::where('event', 'role_assigned')
            ->where('auditable_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertNotNull($audit->new_values);
        $this->assertSame('ui-action', $audit->new_values['modification_origin']);
    }

    public function test_audit_includes_context_when_provided(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();

        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM, ['reason' => 'test']);

        $audit = Audit::where('event', 'role_assigned')
            ->where('auditable_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertNotNull($audit->new_values);
        $this->assertArrayHasKey('context', $audit->new_values);
        $this->assertSame(['reason' => 'test'], $audit->new_values['context']);
    }

    public function test_assign_role_with_audit_accepts_array_of_roles(): void
    {
        $user = User::factory()->createOne();
        $roles = Role::factory()->count(2)->create()->all();

        $user->assignRoleWithAudit($roles, RoleModificationOriginEnum::SYSTEM);

        $audit = Audit::where('event', 'role_assigned')
            ->where('auditable_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $assignedRoles = $audit->new_values['assigned_roles'];
        $this->assertCount(2, $assignedRoles);
    }

    public function test_assign_role_with_audit_accepts_collection_of_roles(): void
    {
        $user = User::factory()->createOne();
        $roles = Role::factory()->count(2)->create();

        $user->assignRoleWithAudit($roles, RoleModificationOriginEnum::SYSTEM);

        $audit = Audit::where('event', 'role_assigned')
            ->where('auditable_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $assignedRoles = $audit->new_values['assigned_roles'];
        $this->assertCount(2, $assignedRoles);
    }

    public function test_audit_excludes_context_when_empty(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();

        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM);

        $audit = Audit::where('event', 'role_assigned')
            ->where('auditable_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertNotNull($audit->new_values);
        $this->assertArrayNotHasKey('context', $audit->new_values);
    }
}
