<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\App\Pages;

use App\Domains\Auth\Enums\RoleModificationOrigin;
use App\Domains\Auth\Enums\SystemRole;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;
use App\Filament\App\Pages\EnvironmentLockdown;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(EnvironmentLockdown::class)]
final class EnvironmentLockdownTest extends TestCase
{
    private Role $adminRole;

    private Role $nuRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::factory()->create(['name' => 'Admin']);
        $this->nuRole = Role::query()->where('name', SystemRole::NorthwesternUser->value)->firstOrFail();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('filament.app.environment-lockdown'))
            ->assertRedirect('/app/login');
    }

    public function test_shows_the_page_to_users_with_only_the_northwestern_user_role(): void
    {
        $user = User::factory()->create();
        $user->assignRoleWithAudit($this->nuRole, RoleModificationOrigin::System);

        $this->actingAs($user)
            ->get(route('filament.app.environment-lockdown'))
            ->assertOk()
            ->assertSee('You don\'t have access to this environment.')
            ->assertSee('Sign Out');
    }

    public function test_shows_the_page_to_users_with_no_roles(): void
    {
        $this->actingAs(User::factory()->affiliate()->create())
            ->get(route('filament.app.environment-lockdown'))
            ->assertOk()
            ->assertSee('Access Restricted');
    }

    public function test_redirects_users_with_a_non_default_role_home(): void
    {
        $user = User::factory()->create();
        $user->assignRoleWithAudit([$this->adminRole, $this->nuRole], RoleModificationOrigin::System);

        $this->actingAs($user)
            ->get(route('filament.app.environment-lockdown'))
            ->assertRedirect('/');
    }

    public function test_lockdown_applies_to_app_panel_pages(): void
    {
        config(['platform.lockdown.enabled' => true]);

        $this->actingAs(User::factory()->create())
            ->get('/app')
            ->assertRedirect(route('filament.app.environment-lockdown'));
    }

    public function test_lockdown_lets_users_with_a_non_default_role_into_the_app_panel(): void
    {
        config(['platform.lockdown.enabled' => true]);

        $user = User::factory()->create();
        $user->assignRoleWithAudit($this->adminRole, RoleModificationOrigin::System);

        $this->actingAs($user)
            ->get('/app')
            ->assertOk();
    }
}
