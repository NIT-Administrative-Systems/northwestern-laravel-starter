<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Domains\Auth\Enums\SystemRoleEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;
use App\Http\Middleware\EnvironmentLockdown;
use Illuminate\Support\Facades\Route;
use Lab404\Impersonate\Services\ImpersonateManager;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(EnvironmentLockdown::class)]
class EnvironmentLockdownTest extends TestCase
{
    private string $endpoint = '/lockdown-test';

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', EnvironmentLockdown::class])->get($this->endpoint, function () {
            return response()->json(['ok' => true]);
        });

        $this->adminRole = Role::factory()->create(['name' => 'Admin']);
    }

    public function test_allows_request_when_lockdown_is_disabled(): void
    {
        config(['platform.lockdown.enabled' => false]);

        $user = User::factory()->create();
        $user->assignRole(SystemRoleEnum::NORTHWESTERN_USER);

        $this->actingAs($user)
            ->get($this->endpoint)
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_allows_guest_users_through(): void
    {
        config(['platform.lockdown.enabled' => true]);

        $this->get($this->endpoint)
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_redirects_users_with_only_northwestern_user_role(): void
    {
        config(['platform.lockdown.enabled' => true]);

        $user = User::factory()->create();
        $user->assignRole(SystemRoleEnum::NORTHWESTERN_USER);

        $this->actingAs($user)
            ->get($this->endpoint)
            ->assertRedirect(route('platform.environment-lockdown'));
    }

    public function test_redirects_users_with_no_roles(): void
    {
        config(['platform.lockdown.enabled' => true]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get($this->endpoint)
            ->assertRedirect(route('platform.environment-lockdown'));
    }

    public function test_allows_users_with_non_default_roles(): void
    {
        config(['platform.lockdown.enabled' => true]);

        $user = User::factory()->create();
        $user->assignRole([$this->adminRole, SystemRoleEnum::NORTHWESTERN_USER]);

        $this->actingAs($user)
            ->get($this->endpoint)
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_allows_users_with_only_non_default_roles(): void
    {
        config(['platform.lockdown.enabled' => true]);

        $user = User::factory()->create();
        $user->assignRole($this->adminRole);

        $this->actingAs($user)
            ->get($this->endpoint)
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_allows_users_with_multiple_non_default_roles(): void
    {
        config(['platform.lockdown.enabled' => true]);

        $editorRole = Role::factory()->create(['name' => 'Editor']);
        $user = User::factory()->create();
        $user->assignRole([$this->adminRole, $editorRole]);

        $this->actingAs($user)
            ->get($this->endpoint)
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_allows_impersonated_users(): void
    {
        config(['platform.lockdown.enabled' => true]);

        $impersonator = User::factory()->create();
        $impersonator->assignRole($this->adminRole);

        $target = User::factory()->create();

        $impersonateManager = $this->mock(ImpersonateManager::class);
        $impersonateManager->allows('isImpersonating')
            ->andReturns(true);

        $this->actingAs($target)
            ->get($this->endpoint)
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_redirects_to_lockdown_when_user_has_only_default_role_on_non_exempt_route(): void
    {
        config(['platform.lockdown.enabled' => true]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get($this->endpoint)
            ->assertRedirect(route('platform.environment-lockdown'));
    }

    public function test_all_exempted_routes_allow_users_with_only_default_role(): void
    {
        config(['platform.lockdown.enabled' => true]);

        $user = User::factory()->create();

        foreach (EnvironmentLockdown::EXEMPTED_ROUTES as $routeName) {
            Route::middleware(['web', EnvironmentLockdown::class])
                ->get('/test-route-' . str_replace('.', '-', $routeName), function () {
                    return response()->json(['ok' => true]);
                })
                ->name($routeName);

            $this->actingAs($user)
                ->get('/test-route-' . str_replace('.', '-', $routeName))
                ->assertOk()
                ->assertJson(['ok' => true]);
        }
    }

    public function test_lockdown_takes_precedence_over_non_exempted_routes(): void
    {
        config(['platform.lockdown.enabled' => true]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get($this->endpoint)
            ->assertRedirect(route('platform.environment-lockdown'));
    }
}
