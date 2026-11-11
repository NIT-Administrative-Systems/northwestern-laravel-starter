<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\App\Starter\Pages;

use App\Domains\Access\Enums\RoleModificationOrigin;
use App\Domains\Access\Enums\SystemRole;
use App\Domains\Access\Models\Role;
use App\Domains\User\Models\User;
use Mockery;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class EnvironmentLockdownTest extends TestCase
{
    private Role $adminRole;

    private Role $nuRole;

    protected function setUp(): void
    {
        parent::setUp();

        config(['platform.lockdown.enabled' => true]);

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

    public function test_redirects_home_when_lockdown_is_off(): void
    {
        config(['platform.lockdown.enabled' => false]);

        $this->actingAs(User::factory()->affiliate()->create())
            ->get(route('filament.app.environment-lockdown'))
            ->assertRedirect('/');
    }

    public function test_redirects_an_administrator_impersonating_someone_without_a_role_home(): void
    {
        $impersonate = Mockery::mock();
        $impersonate->shouldReceive('isImpersonating')->andReturn(true);
        $impersonate->shouldReceive('getImpersonatorId')->andReturn(null);
        $this->app->instance('impersonate', $impersonate);

        $this->actingAs(User::factory()->affiliate()->create())
            ->get(route('filament.app.environment-lockdown'))
            ->assertRedirect('/');
    }

    public function test_lockdown_applies_to_app_panel_pages(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/app')
            ->assertRedirect(route('filament.app.environment-lockdown'));
    }

    public function test_lockdown_lets_users_with_a_non_default_role_into_the_app_panel(): void
    {
        $user = User::factory()->create();
        $user->assignRoleWithAudit($this->adminRole, RoleModificationOrigin::System);

        $this->actingAs($user)
            ->get('/app')
            ->assertOk();
    }
}
