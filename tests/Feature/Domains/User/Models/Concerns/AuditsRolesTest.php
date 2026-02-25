<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Models\Concerns;

use App\Domains\Auth\Enums\RoleModificationOriginEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\Audit;
use App\Domains\User\Models\Concerns\AuditsRoles;
use App\Domains\User\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
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

        $this->assertArrayHasKey('roles', $audit->old_values);
        $this->assertArrayHasKey('roles', $audit->new_values);

        /** @var list<array<string, mixed>> $afterRoles */
        $afterRoles = $audit->new_values['roles'];
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
        $beforeRoles = $audit->old_values['roles'];
        $beforeRoleNames = collect($beforeRoles)->pluck('name')->all();
        $this->assertContains($role->name, $beforeRoleNames);

        /** @var list<array<string, mixed>> $afterRoles */
        $afterRoles = $audit->new_values['roles'];
        $afterRoleNames = collect($afterRoles)->pluck('name')->all();
        $this->assertNotContains($role->name, $afterRoleNames);
    }

    public function test_audit_includes_modification_origin_as_tag(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();

        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::UI_ACTION);

        $audit = Audit::where('event', 'role_assigned')
            ->where('auditable_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringContainsString('ui-action', $audit->tags);
    }

    public function test_audit_includes_context_as_tags(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();

        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM, ['reason' => 'test']);

        $audit = Audit::where('event', 'role_assigned')
            ->where('auditable_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringContainsString('system', $audit->tags);
        $this->assertStringContainsString('reason: test', $audit->tags);
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

        /** @var list<array<string, mixed>> $afterRoles */
        $afterRoles = $audit->new_values['roles'];
        $afterRoleNames = collect($afterRoles)->pluck('name')->all();
        foreach ($roles as $role) {
            $this->assertContains($role->name, $afterRoleNames);
        }
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

        /** @var list<array<string, mixed>> $afterRoles */
        $afterRoles = $audit->new_values['roles'];
        $afterRoleNames = collect($afterRoles)->pluck('name')->all();
        foreach ($roles as $role) {
            $this->assertContains($role->name, $afterRoleNames);
        }
    }

    public function test_audit_handles_model_deleted_during_role_change(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();

        $intercepted = false;

        DB::listen(function (QueryExecuted $query) use ($user, &$intercepted) {
            if (! $intercepted && str_contains($query->sql, 'model_has_roles') && str_contains($query->sql, 'insert')) {
                $intercepted = true;
                DB::table('users')->where('id', $user->id)->delete();
            }
        });

        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM);

        $this->assertTrue($intercepted);

        $audit = Audit::where('event', 'role_assigned')
            ->where('auditable_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame([], $audit->new_values['roles']);
    }

    public function test_audit_excludes_context_from_tags_when_empty(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();

        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::SYSTEM);

        $audit = Audit::where('event', 'role_assigned')
            ->where('auditable_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        // Tags should only contain the origin, no context entries
        $this->assertSame('system', $audit->tags);
    }
}
