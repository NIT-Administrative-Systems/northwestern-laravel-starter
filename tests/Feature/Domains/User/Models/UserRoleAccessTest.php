<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Models;

use App\Domains\Access\Enums\RoleModificationOrigin;
use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Models\Role;
use App\Domains\User\Models\User;
use App\Providers\Filament\AdministrationPanelProvider;
use App\Providers\Filament\AppPanelProvider;
use Filament\Facades\Filament;
use Filament\Panel;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

final class UserRoleAccessTest extends TestCase
{
    public function test_assign_role_is_not_publicly_callable(): void
    {
        $reflection = new ReflectionMethod(User::class, 'assignRole');

        $this->assertTrue($reflection->isPrivate());
    }

    public function test_remove_role_is_not_publicly_callable(): void
    {
        $reflection = new ReflectionMethod(User::class, 'removeRole');

        $this->assertTrue($reflection->isPrivate());
    }

    public function test_sync_roles_is_not_publicly_callable(): void
    {
        $reflection = new ReflectionMethod(User::class, 'syncRoles');

        $this->assertTrue($reflection->isPrivate());
    }

    public function test_assign_role_with_audit_is_publicly_callable(): void
    {
        $reflection = new ReflectionMethod(User::class, 'assignRoleWithAudit');

        $this->assertTrue($reflection->isPublic());
    }

    public function test_remove_role_with_audit_is_publicly_callable(): void
    {
        $reflection = new ReflectionMethod(User::class, 'removeRoleWithAudit');

        $this->assertTrue($reflection->isPublic());
    }

    public function test_can_access_administration_panel_with_permission(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $role->givePermissionTo(SystemPermission::AccessAdministrationPanel);
        $user->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $panel = Mockery::mock(Panel::class);
        $panel->expects('getId')->andReturn('administration');

        $this->assertTrue($user->canAccessPanel($panel));
    }

    public function test_cannot_access_administration_panel_without_permission(): void
    {
        $user = User::factory()->createOne();

        $panel = Mockery::mock(Panel::class);
        $panel->expects('getId')->andReturn('administration');

        $this->assertFalse($user->canAccessPanel($panel));
    }

    public function test_a_deactivated_person_cannot_use_either_panel(): void
    {
        $user = User::factory()->create(['netid_inactive' => true]);
        $user->givePermissionTo(SystemPermission::AccessAdministrationPanel);

        $this->assertFalse($user->canAccessPanel(Filament::getPanel(AppPanelProvider::ID)));
        $this->assertFalse($user->canAccessPanel(Filament::getPanel(AdministrationPanelProvider::ID)));

        // A session signed in before the deactivation is refused on its next request.
        $this->actingAs($user)->get('/app')->assertForbidden();
        $this->actingAs($user)->get('/administration')->assertForbidden();
    }

    public function test_a_deleted_person_cannot_use_either_panel(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::AccessAdministrationPanel);
        $user->delete();

        $this->assertFalse($user->canAccessPanel(Filament::getPanel(AppPanelProvider::ID)));
        $this->assertFalse($user->canAccessPanel(Filament::getPanel(AdministrationPanelProvider::ID)));
    }

    public function test_an_active_person_can_use_the_app_panel(): void
    {
        $user = User::factory()->create(['netid_inactive' => false]);

        $this->assertTrue($user->canAccessPanel(Filament::getPanel(AppPanelProvider::ID)));
    }
}
