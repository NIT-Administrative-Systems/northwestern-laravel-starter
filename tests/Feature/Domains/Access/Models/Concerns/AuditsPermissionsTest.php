<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Access\Models\Concerns;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Models\Concerns\AuditsPermissions;
use App\Domains\Access\Models\Role;
use App\Domains\Core\Models\Audit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;

#[CoversTrait(AuditsPermissions::class)]
final class AuditsPermissionsTest extends TestCase
{
    public function test_changing_a_roles_permissions_is_audited(): void
    {
        $role = Role::factory()->createOne();

        $role->syncPermissionsWithAudit([SystemPermission::ViewUsers]);

        $audit = Audit::where('event', 'permissions_modified')
            ->where('auditable_type', Role::class)
            ->where('auditable_id', $role->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('permissions_modified', $audit->event);
    }

    public function test_setting_the_same_permissions_records_nothing(): void
    {
        $role = Role::factory()->createOne();
        $role->givePermissionTo(SystemPermission::ViewUsers);

        $auditCountBefore = Audit::where('event', 'permissions_modified')
            ->where('auditable_id', $role->id)
            ->count();

        $role->syncPermissionsWithAudit([SystemPermission::ViewUsers]);

        $auditCountAfter = Audit::where('event', 'permissions_modified')
            ->where('auditable_id', $role->id)
            ->count();

        $this->assertSame($auditCountBefore, $auditCountAfter);
    }

    public function test_the_audit_shows_added_permissions(): void
    {
        $role = Role::factory()->createOne();

        $role->syncPermissionsWithAudit([SystemPermission::ViewUsers]);

        $audit = Audit::where('event', 'permissions_modified')
            ->where('auditable_id', $role->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertNotNull($audit->new_values);
        $this->assertArrayHasKey('permissions', $audit->new_values);

        /** @var list<array<string, mixed>> $afterPermissions */
        $afterPermissions = $audit->new_values['permissions'];
        $addedPermission = collect($afterPermissions)->firstWhere('name', SystemPermission::ViewUsers->value);
        $this->assertNotNull($addedPermission);
        $this->assertArrayHasKey('name', $addedPermission);
        $this->assertArrayHasKey('label', $addedPermission);
        $this->assertArrayHasKey('system_managed', $addedPermission);
        $this->assertArrayHasKey('api_relevant', $addedPermission);
    }

    public function test_the_audit_shows_removed_permissions(): void
    {
        $role = Role::factory()->createOne();
        $role->givePermissionTo(SystemPermission::ViewUsers);

        $role->syncPermissionsWithAudit([]);

        $audit = Audit::where('event', 'permissions_modified')
            ->where('auditable_id', $role->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertNotNull($audit->old_values);
        $this->assertNotNull($audit->new_values);

        /** @var list<array<string, mixed>> $beforePermissions */
        $beforePermissions = $audit->old_values['permissions'];
        $beforeNames = collect($beforePermissions)->pluck('name')->all();
        $this->assertContains(SystemPermission::ViewUsers->value, $beforeNames);

        /** @var list<array<string, mixed>> $afterPermissions */
        $afterPermissions = $audit->new_values['permissions'];
        $this->assertEmpty($afterPermissions);
    }

    public function test_a_role_deleted_during_the_change_is_still_audited(): void
    {
        $role = Role::factory()->createOne();
        $role->givePermissionTo(SystemPermission::ViewUsers);
        $role->load('permissions');

        $intercepted = false;

        DB::listen(function (QueryExecuted $query) use ($role, &$intercepted) {
            if (! $intercepted && str_contains($query->sql, 'insert') && str_contains($query->sql, 'role_has_permissions')) {
                $intercepted = true;
                DB::table('roles')->where('id', $role->id)->delete();
            }
        });

        $role->syncPermissionsWithAudit([SystemPermission::EditUsers]);

        $this->assertTrue($intercepted);

        $audit = Audit::where('event', 'permissions_modified')
            ->where('auditable_id', $role->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame([], $audit->new_values['permissions']);
    }

    public function test_the_audit_has_the_permissions_before_and_after(): void
    {
        $role = Role::factory()->createOne();
        $role->givePermissionTo(SystemPermission::ViewUsers);

        $role->syncPermissionsWithAudit([SystemPermission::EditUsers]);

        $audit = Audit::where('event', 'permissions_modified')
            ->where('auditable_id', $role->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertNotNull($audit->old_values);
        $this->assertNotNull($audit->new_values);

        /** @var list<array<string, mixed>> $beforePermissions */
        $beforePermissions = $audit->old_values['permissions'];
        $beforeNames = collect($beforePermissions)->pluck('name')->all();
        $this->assertContains(SystemPermission::ViewUsers->value, $beforeNames);
        $this->assertNotContains(SystemPermission::EditUsers->value, $beforeNames);

        /** @var list<array<string, mixed>> $afterPermissions */
        $afterPermissions = $audit->new_values['permissions'];
        $afterNames = collect($afterPermissions)->pluck('name')->all();
        $this->assertContains(SystemPermission::EditUsers->value, $afterNames);
        $this->assertNotContains(SystemPermission::ViewUsers->value, $afterNames);
    }
}
