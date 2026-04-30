<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources\Roles;

use App\Domains\Auth\Enums\RoleTypeEnum;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\Audit;
use App\Domains\User\Models\User;
use App\Filament\Resources\Roles\Pages\RoleDefinitionHistory;
use App\Filament\Resources\Roles\Tables\RoleDefinitionHistoryTable;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;
use Tests\TestCase;

final class RoleDefinitionHistoryTest extends TestCase
{
    private User $admin;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(SystemPermission::ViewRoles);
        $this->admin->givePermissionTo(SystemPermission::AccessAdministrationPanel);

        $this->role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();
    }

    public function test_history_page_renders_for_user_with_view_roles(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(RoleDefinitionHistory::class, ['record' => $this->role->getKey()])
            ->assertSuccessful();
    }

    public function test_history_page_denied_without_view_roles(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::AccessAdministrationPanel);

        $this->actingAs($user);

        Livewire::test(RoleDefinitionHistory::class, ['record' => $this->role->getKey()])
            ->assertForbidden();
    }

    public function test_history_page_does_not_require_view_audit_logs(): void
    {
        // Admin has ViewRoles but NOT ViewAuditLogs — should still work
        $this->assertFalse($this->admin->hasPermissionTo(SystemPermission::ViewAuditLogs));

        $this->actingAs($this->admin);

        Livewire::test(RoleDefinitionHistory::class, ['record' => $this->role->getKey()])
            ->assertSuccessful();
    }

    public function test_history_table_shows_role_audit_events(): void
    {
        $this->actingAs($this->admin);

        // Create an audit record for this role
        $audit = Audit::create([
            'user_type' => User::class,
            'user_id' => $this->admin->id,
            'event' => 'created',
            'auditable_type' => Role::class,
            'auditable_id' => $this->role->getKey(),
            'old_values' => [],
            'new_values' => ['name' => $this->role->name],
        ]);

        Livewire::test(RoleDefinitionHistory::class, ['record' => $this->role->getKey()])
            ->assertCanSeeTableRecords([$audit]);
    }

    public function test_history_table_shows_updated_event(): void
    {
        $this->actingAs($this->admin);

        $audit = Audit::create([
            'user_type' => User::class,
            'user_id' => $this->admin->id,
            'event' => 'updated',
            'auditable_type' => Role::class,
            'auditable_id' => $this->role->getKey(),
            'old_values' => ['name' => 'Old Name'],
            'new_values' => ['name' => 'New Name'],
        ]);

        Livewire::test(RoleDefinitionHistory::class, ['record' => $this->role->getKey()])
            ->assertCanSeeTableRecords([$audit]);
    }

    public function test_history_table_shows_permissions_modified_event(): void
    {
        $this->actingAs($this->admin);

        $this->role->syncPermissionsWithAudit([SystemPermission::ViewUsers]);

        $audit = Audit::query()
            ->where('auditable_type', Role::class)
            ->where('auditable_id', $this->role->getKey())
            ->where('event', 'permissions_modified')
            ->latest('id')
            ->first();

        $this->assertInstanceOf(Audit::class, $audit);

        Livewire::test(RoleDefinitionHistory::class, ['record' => $this->role->getKey()])
            ->assertCanSeeTableRecords([$audit]);
    }

    public function test_history_table_only_shows_audits_for_viewed_role(): void
    {
        $this->actingAs($this->admin);

        $otherRole = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();

        $otherAudit = Audit::create([
            'user_type' => User::class,
            'user_id' => $this->admin->id,
            'event' => 'created',
            'auditable_type' => Role::class,
            'auditable_id' => $otherRole->getKey(),
            'old_values' => [],
            'new_values' => ['name' => $otherRole->name],
        ]);

        Livewire::test(RoleDefinitionHistory::class, ['record' => $this->role->getKey()])
            ->assertCanNotSeeTableRecords([$otherAudit]);
    }

    public function test_summarize_changes_for_created_event(): void
    {
        $audit = new Audit();
        $audit->event = 'created';
        $audit->old_values = [];
        $audit->new_values = ['name' => 'Test Role'];

        $result = RoleDefinitionHistoryTable::summarizeChanges($audit);

        $this->assertInstanceOf(HtmlString::class, $result);
        $this->assertStringContainsString('Role created', $result->toHtml());
    }

    public function test_summarize_changes_for_deleted_event(): void
    {
        $audit = new Audit();
        $audit->event = 'deleted';
        $audit->old_values = ['name' => 'Test Role'];
        $audit->new_values = [];

        $result = RoleDefinitionHistoryTable::summarizeChanges($audit);

        $this->assertStringContainsString('Role deleted', $result->toHtml());
    }

    public function test_summarize_changes_for_restored_event(): void
    {
        $audit = new Audit();
        $audit->event = 'restored';
        $audit->old_values = [];
        $audit->new_values = [];

        $result = RoleDefinitionHistoryTable::summarizeChanges($audit);

        $this->assertStringContainsString('Role restored', $result->toHtml());
    }

    public function test_summarize_changes_for_name_update(): void
    {
        $audit = new Audit();
        $audit->event = 'updated';
        $audit->old_values = ['name' => 'Old Name'];
        $audit->new_values = ['name' => 'New Name'];

        $result = RoleDefinitionHistoryTable::summarizeChanges($audit);

        $this->assertStringContainsString('Name', $result->toHtml());
        $this->assertStringContainsString('Old Name', $result->toHtml());
        $this->assertStringContainsString('New Name', $result->toHtml());
        $this->assertStringContainsString('<svg', $result->toHtml());
    }

    public function test_summarize_changes_for_multiple_attribute_updates(): void
    {
        $audit = new Audit();
        $audit->event = 'updated';
        $audit->old_values = ['name' => 'Old Name', 'assignment_locked' => false];
        $audit->new_values = ['name' => 'New Name', 'assignment_locked' => true];

        $result = RoleDefinitionHistoryTable::summarizeChanges($audit);

        $this->assertStringContainsString('Name', $result->toHtml());
        $this->assertStringContainsString('Assignment locked', $result->toHtml());
    }

    public function test_summarize_changes_for_permissions_added(): void
    {
        $audit = new Audit();
        $audit->event = 'permissions_modified';
        $audit->old_values = ['permissions' => []];
        $audit->new_values = ['permissions' => [
            ['name' => 'view-users', 'label' => 'View Users', 'system_managed' => false, 'api_relevant' => false],
            ['name' => 'edit-users', 'label' => 'Edit Users', 'system_managed' => false, 'api_relevant' => false],
        ]];

        $result = RoleDefinitionHistoryTable::summarizeChanges($audit);

        $this->assertStringContainsString('+ View Users', $result->toHtml());
        $this->assertStringContainsString('+ Edit Users', $result->toHtml());
        $this->assertStringContainsString('success', $result->toHtml());
        $this->assertStringNotContainsString('danger', $result->toHtml());
    }

    public function test_summarize_changes_for_permissions_removed(): void
    {
        $audit = new Audit();
        $audit->event = 'permissions_modified';
        $audit->old_values = ['permissions' => [
            ['name' => 'view-users', 'label' => 'View Users', 'system_managed' => false, 'api_relevant' => false],
        ]];
        $audit->new_values = ['permissions' => []];

        $result = RoleDefinitionHistoryTable::summarizeChanges($audit);

        $this->assertStringContainsString('− View Users', $result->toHtml());
        $this->assertStringContainsString('danger', $result->toHtml());
        $this->assertStringNotContainsString('success', $result->toHtml());
    }

    public function test_summarize_changes_for_permissions_added_and_removed(): void
    {
        $audit = new Audit();
        $audit->event = 'permissions_modified';
        $audit->old_values = ['permissions' => [
            ['name' => 'view-users', 'label' => 'View Users', 'system_managed' => false, 'api_relevant' => false],
        ]];
        $audit->new_values = ['permissions' => [
            ['name' => 'edit-users', 'label' => 'Edit Users', 'system_managed' => false, 'api_relevant' => false],
        ]];

        $result = RoleDefinitionHistoryTable::summarizeChanges($audit);

        $this->assertStringContainsString('+ Edit Users', $result->toHtml());
        $this->assertStringContainsString('success', $result->toHtml());
        $this->assertStringContainsString('− View Users', $result->toHtml());
        $this->assertStringContainsString('danger', $result->toHtml());
    }

    public function test_summarize_changes_for_role_type_change_resolves_label(): void
    {
        $roleType = $this->role->role_type;
        $audit = new Audit();
        $audit->event = 'updated';
        $audit->old_values = ['role_type_id' => $roleType->id];
        $audit->new_values = ['role_type_id' => $roleType->id];

        // Same value should produce "Role updated" fallback
        $result = RoleDefinitionHistoryTable::summarizeChanges($audit);
        $this->assertStringContainsString('Role updated', $result->toHtml());
    }

    public function test_history_table_empty_state_renders(): void
    {
        $this->actingAs($this->admin);

        // Delete all audits for this role to test empty state
        Audit::query()
            ->where('auditable_type', Role::class)
            ->where('auditable_id', $this->role->getKey())
            ->delete();

        Livewire::test(RoleDefinitionHistory::class, ['record' => $this->role->getKey()])
            ->assertSuccessful()
            ->assertSee('No definition history yet');
    }
}
