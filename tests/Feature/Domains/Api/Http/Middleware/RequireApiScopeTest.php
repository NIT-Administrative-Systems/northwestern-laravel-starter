<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Api\Http\Middleware;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Enums\SystemRole;
use App\Domains\Access\Models\Role;
use App\Domains\Api\Http\Middleware\AuthenticatePassportToken;
use App\Domains\Api\Http\Middleware\RequireApiScope;
use App\Domains\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\IssuesPersonalAccessTokens;
use Tests\Concerns\IssuesServiceClientTokens;
use Tests\TestCase;

#[CoversClass(RequireApiScope::class)]
final class RequireApiScopeTest extends TestCase
{
    use IssuesPersonalAccessTokens, IssuesServiceClientTokens;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', AuthenticatePassportToken::class, RequireApiScope::class . ':view-users'])
            ->get('/api/test/users', fn (Request $request) => response()->json([
                'can_view_users' => $request->user()?->can(SystemPermission::ViewUsers),
            ]));
    }

    public function test_a_personal_token_reaches_only_what_its_scopes_name(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::ViewUsers);

        [$scoped] = $this->personalAccessToken($user, ['view-users']);
        [$unscoped] = $this->personalAccessToken($user);

        $this->withToken($scoped)->getJson('/api/test/users')->assertOk()->assertJsonPath('can_view_users', true);
        $this->withToken($unscoped)->getJson('/api/test/users')->assertForbidden();
    }

    // The super-administrator shortcut in Gate::before grants every ability in the browser,
    // including ones no permission covers. A token gets no such shortcut.
    public function test_a_super_administrators_token_has_no_shortcut(): void
    {
        Gate::define('test-unrestricted-ability', fn (): bool => false);
        Route::middleware(['api', AuthenticatePassportToken::class])
            ->get('/api/test/ability', fn (Request $request) => response()->json(['allowed' => $request->user()?->can('test-unrestricted-ability')]));

        $superAdmin = User::factory()->create();
        $superAdmin->roles()->attach(Role::query()->where('name', SystemRole::SuperAdministrator)->sole());
        $this->assertTrue($superAdmin->can('test-unrestricted-ability'));

        [$scoped] = $this->personalAccessToken($superAdmin, ['view-users']);
        [$unscoped] = $this->personalAccessToken($superAdmin);

        $this->withToken($scoped)->getJson('/api/test/ability')->assertOk()->assertJsonPath('allowed', false);
        $this->withToken($unscoped)->getJson('/api/test/users')->assertForbidden();
    }

    // A service client acts as its API user, whose roles are its ceiling.
    public function test_a_service_client_holds_every_scope(): void
    {
        [$token] = $this->serviceClientToken(User::factory()->api()->create());

        $this->withToken($token)->getJson('/api/test/users')->assertOk();
    }
}
