<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Models\Concerns;

use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\Audit;
use App\Domains\User\Models\Concerns\AuditsPermissions;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;

#[CoversTrait(AuditsPermissions::class)]
class AuditsPermissionsTest extends TestCase
{
    public function test_sync_permissions_creates_audit_when_permissions_change(): void
    {
        $role = Role::factory()->createOne();

        $role->syncPermissionsWithAudit([PermissionEnum::VIEW_USERS]);

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
        $role->givePermissionTo(PermissionEnum::VIEW_USERS);

        $auditCountBefore = Audit::where('event', 'permissions_modified')
            ->where('auditable_id', $role->id)
            ->count();

        $role->syncPermissionsWithAudit([PermissionEnum::VIEW_USERS]);

        $auditCountAfter = Audit::where('event', 'permissions_modified')
            ->where('auditable_id', $role->id)
            ->count();

        $this->assertSame($auditCountBefore, $auditCountAfter);
    }

    public function test_sync_permissions_audit_captures_added_permissions(): void
    {
        $role = Role::factory()->createOne();

        $role->syncPermissionsWithAudit([PermissionEnum::VIEW_USERS]);

        $audit = Audit::where('event', 'permissions_modified')
            ->where('auditable_id', $role->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertNotNull($audit->new_values);
        $this->assertArrayHasKey('added_permissions', $audit->new_values);

        /** @var list<array<string, mixed>> $addedPermissions */
        $addedPermissions = $audit->new_values['added_permissions'];
        $addedPermission = collect($addedPermissions)->firstWhere('name', PermissionEnum::VIEW_USERS->value);
        $this->assertNotNull($addedPermission);
        $this->assertArrayHasKey('name', $addedPermission);
        $this->assertArrayHasKey('label', $addedPermission);
        $this->assertArrayHasKey('system_managed', $addedPermission);
        $this->assertArrayHasKey('api_relevant', $addedPermission);
    }

    public function test_sync_permissions_audit_captures_removed_permissions(): void
    {
        $role = Role::factory()->createOne();
        $role->givePermissionTo(PermissionEnum::VIEW_USERS);

        $role->syncPermissionsWithAudit([]);

        $audit = Audit::where('event', 'permissions_modified')
            ->where('auditable_id', $role->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertNotNull($audit->new_values);
        $this->assertArrayHasKey('removed_permissions', $audit->new_values);

        /** @var list<array<string, mixed>> $removedPermissions */
        $removedPermissions = $audit->new_values['removed_permissions'];
        $removedNames = collect($removedPermissions)->pluck('name')->all();
        $this->assertContains(PermissionEnum::VIEW_USERS->value, $removedNames);
    }

    public function test_sync_permissions_audit_captures_before_and_after_state(): void
    {
        $role = Role::factory()->createOne();
        $role->givePermissionTo(PermissionEnum::VIEW_USERS);

        $role->syncPermissionsWithAudit([PermissionEnum::EDIT_USERS]);

        $audit = Audit::where('event', 'permissions_modified')
            ->where('auditable_id', $role->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertNotNull($audit->old_values);
        $this->assertNotNull($audit->new_values);

        /** @var list<array<string, mixed>> $beforePermissions */
        $beforePermissions = $audit->old_values['permissions_before_change'];
        $beforeNames = collect($beforePermissions)->pluck('name')->all();
        $this->assertContains(PermissionEnum::VIEW_USERS->value, $beforeNames);
        $this->assertNotContains(PermissionEnum::EDIT_USERS->value, $beforeNames);

        /** @var list<array<string, mixed>> $afterPermissions */
        $afterPermissions = $audit->new_values['permissions_after_change'];
        $afterNames = collect($afterPermissions)->pluck('name')->all();
        $this->assertContains(PermissionEnum::EDIT_USERS->value, $afterNames);
        $this->assertNotContains(PermissionEnum::VIEW_USERS->value, $afterNames);
    }
}
