<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Models\Concerns;

use App\Domains\Auth\Enums\RoleModificationOrigin;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\Audit;
use App\Domains\User\Models\Concerns\AuditsRoles;
use App\Domains\User\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;

#[CoversTrait(AuditsRoles::class)]
final class AuditsRolesTest extends TestCase
{
    private User $user;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->createOne();
        $this->role = Role::factory()->createOne();
    }

    public function test_assign_role_with_audit_creates_audit_record(): void
    {
        $this->user->assignRoleWithAudit($this->role, RoleModificationOrigin::System);

        $audit = Audit::where('event', 'role_assigned')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $this->user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('role_assigned', $audit->event);
    }

    public function test_assign_role_with_audit_captures_roles_before_and_after(): void
    {
        $this->user->assignRoleWithAudit($this->role, RoleModificationOrigin::System);

        $audit = Audit::where('event', 'role_assigned')
            ->where('auditable_id', $this->user->id)
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
        $this->assertContains($this->role->name, $afterRoleNames);
    }

    public function test_remove_role_with_audit_creates_audit_record(): void
    {
        $this->user->assignRoleWithAudit($this->role, RoleModificationOrigin::System);

        $this->user->removeRoleWithAudit($this->role, RoleModificationOrigin::System);

        $audit = Audit::where('event', 'role_removed')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $this->user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('role_removed', $audit->event);
    }

    public function test_remove_role_with_audit_captures_correct_diff(): void
    {
        $this->user->assignRoleWithAudit($this->role, RoleModificationOrigin::System);

        $this->user->removeRoleWithAudit($this->role, RoleModificationOrigin::System);

        $audit = Audit::where('event', 'role_removed')
            ->where('auditable_id', $this->user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertNotNull($audit->old_values);
        $this->assertNotNull($audit->new_values);

        /** @var list<array<string, mixed>> $beforeRoles */
        $beforeRoles = $audit->old_values['roles'];
        $beforeRoleNames = collect($beforeRoles)->pluck('name')->all();
        $this->assertContains($this->role->name, $beforeRoleNames);

        /** @var list<array<string, mixed>> $afterRoles */
        $afterRoles = $audit->new_values['roles'];
        $afterRoleNames = collect($afterRoles)->pluck('name')->all();
        $this->assertNotContains($this->role->name, $afterRoleNames);
    }

    public function test_audit_includes_modification_origin_as_tag(): void
    {
        $this->user->assignRoleWithAudit($this->role, RoleModificationOrigin::UiAction);

        $audit = Audit::where('event', 'role_assigned')
            ->where('auditable_id', $this->user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringContainsString('ui-action', (string) $audit->tags);
    }

    public function test_audit_includes_context_as_tags(): void
    {
        $this->user->assignRoleWithAudit($this->role, RoleModificationOrigin::System, ['reason' => 'test']);

        $audit = Audit::where('event', 'role_assigned')
            ->where('auditable_id', $this->user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringContainsString('system', (string) $audit->tags);
        $this->assertStringContainsString('reason: test', (string) $audit->tags);
    }

    public function test_assign_role_with_audit_accepts_array_of_roles(): void
    {
        $roles = Role::factory()->count(2)->create()->all();

        $this->user->assignRoleWithAudit($roles, RoleModificationOrigin::System);

        $audit = Audit::where('event', 'role_assigned')
            ->where('auditable_id', $this->user->id)
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
        $roles = Role::factory()->count(2)->create();

        $this->user->assignRoleWithAudit($roles, RoleModificationOrigin::System);

        $audit = Audit::where('event', 'role_assigned')
            ->where('auditable_id', $this->user->id)
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
        $intercepted = false;

        DB::listen(function (QueryExecuted $query) use (&$intercepted) {
            if (! $intercepted && str_contains($query->sql, 'model_has_roles') && str_contains($query->sql, 'insert')) {
                $intercepted = true;
                DB::table('users')->where('id', $this->user->id)->delete();
            }
        });

        $this->user->assignRoleWithAudit($this->role, RoleModificationOrigin::System);

        $this->assertTrue($intercepted);

        $audit = Audit::where('event', 'role_assigned')
            ->where('auditable_id', $this->user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame([], $audit->new_values['roles']);
    }

    public function test_audit_excludes_context_from_tags_when_empty(): void
    {
        $this->user->assignRoleWithAudit($this->role, RoleModificationOrigin::System);

        $audit = Audit::where('event', 'role_assigned')
            ->where('auditable_id', $this->user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        // Tags should only contain the origin, no context entries
        $this->assertSame('system', $audit->tags);
    }
}
