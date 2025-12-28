<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Platform;

use App\Domains\Auth\Enums\SystemRoleEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;
use App\Http\Controllers\Platform\EnvironmentLockdownController;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(EnvironmentLockdownController::class)]
class EnvironmentLockdownControllerTest extends TestCase
{
    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::factory()->create(['name' => 'Admin']);
    }

    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $this->get(route('platform.environment-lockdown'))
            ->assertRedirect('/auth/type');
    }

    public function test_shows_lockdown_page_for_users_with_only_northwestern_user_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole(SystemRoleEnum::NORTHWESTERN_USER);

        $this->actingAs($user)
            ->get(route('platform.environment-lockdown'))
            ->assertOk()
            ->assertViewIs('platform.environment-lockdown');
    }

    public function test_shows_lockdown_page_for_users_with_no_roles(): void
    {
        $user = User::factory()->affiliate()->create();

        $this->actingAs($user)
            ->get(route('platform.environment-lockdown'))
            ->assertOk()
            ->assertViewIs('platform.environment-lockdown');
    }

    public function test_redirects_to_home_for_users_with_non_default_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole([$this->adminRole, SystemRoleEnum::NORTHWESTERN_USER]);

        $this->actingAs($user)
            ->get(route('platform.environment-lockdown'))
            ->assertRedirect('/');
    }

    public function test_redirects_to_home_for_users_with_only_non_default_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole($this->adminRole);

        $this->actingAs($user)
            ->get(route('platform.environment-lockdown'))
            ->assertRedirect('/');
    }

    public function test_redirects_to_home_for_users_with_multiple_non_default_roles(): void
    {
        $editorRole = Role::factory()->create(['name' => 'Editor']);
        $user = User::factory()->create();
        $user->assignRole([$this->adminRole, $editorRole, SystemRoleEnum::NORTHWESTERN_USER]);

        $this->actingAs($user)
            ->get(route('platform.environment-lockdown'))
            ->assertRedirect('/');
    }
}
