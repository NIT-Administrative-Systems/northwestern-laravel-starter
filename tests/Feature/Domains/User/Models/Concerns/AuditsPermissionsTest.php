<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Models\Concerns;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\Audit;
use App\Domains\User\Models\Concerns\AuditsPermissions;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;

#[CoversTrait(AuditsPermissions::class)]
final class AuditsPermissionsTest extends TestCase
{
    public function test_sync_permissions_creates_audit_when_permissions_change(): void
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

    public function test_sync_permissions_does_not_create_audit_when_no_changes(): void
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

    public function test_sync_permissions_audit_captures_added_permissions(): void
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

    public function test_sync_permissions_audit_captures_removed_permissions(): void
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

    public function test_sync_permissions_handles_model_deleted_during_sync(): void
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

    public function test_sync_permissions_audit_captures_before_and_after_state(): void
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
