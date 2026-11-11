<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Access\Models\Concerns;

use App\Domains\Access\Enums\RoleModificationOrigin;
use App\Domains\Access\Enums\RoleTypeEnum;
use App\Domains\Access\Models\Concerns\AuditsRoles;
use App\Domains\Access\Models\Role;
use App\Domains\Core\Models\Audit;
use App\Domains\User\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
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

    public function test_assigning_a_role_is_audited(): void
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

    public function test_the_assignment_audit_has_the_roles_before_and_after(): void
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

    public function test_removing_a_role_is_audited(): void
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

    public function test_the_removal_audit_shows_which_role_went(): void
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

    public function test_the_audit_is_tagged_with_its_origin(): void
    {
        $this->user->assignRoleWithAudit($this->role, RoleModificationOrigin::UiAction);

        $audit = Audit::where('event', 'role_assigned')
            ->where('auditable_id', $this->user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringContainsString('ui-action', (string) $audit->tags);
    }

    public function test_context_is_added_to_the_audit_as_tags(): void
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

    public function test_several_roles_can_be_assigned_as_an_array(): void
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

    public function test_several_roles_can_be_assigned_as_a_collection(): void
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

    public function test_a_user_deleted_during_the_change_is_still_audited(): void
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

    public function test_no_context_adds_no_tags(): void
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

    public function test_an_interface_role_cannot_be_assigned_to_an_api_user(): void
    {
        $apiUser = User::factory()->api()->create();
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();

        try {
            $apiUser->assignRoleWithAudit($role, RoleModificationOrigin::System);
            $this->fail('An API user was given an interface role.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('An API Integration role can only be given to an API user, and other roles only to people.', $e->getMessage());
        }

        $this->assertFalse($apiUser->fresh()->hasRole($role));
        $this->assertFalse(Audit::query()->where('event', 'role_assigned')->where('auditable_id', $apiUser->id)->exists());
    }

    public function test_an_api_integration_role_cannot_be_assigned_to_a_person(): void
    {
        $apiRole = Role::factory()->forRoleType(RoleTypeEnum::ApiIntegration)->create();

        $this->expectException(InvalidArgumentException::class);

        // One mismatch in a list refuses the whole assignment.
        $this->user->assignRoleWithAudit([$this->role, $apiRole], RoleModificationOrigin::System);
    }

    public function test_an_api_integration_role_can_be_assigned_to_an_api_user(): void
    {
        $apiUser = User::factory()->api()->create();
        $apiRole = Role::factory()->forRoleType(RoleTypeEnum::ApiIntegration)->create();

        $apiUser->assignRoleWithAudit(collect([$apiRole]), RoleModificationOrigin::System);

        $this->assertTrue($apiUser->fresh()->hasRole($apiRole));
    }
}
