<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Http\Controllers\LogoutSelectionController;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LogoutSelectionController::class)]
class LogoutSelectionControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('auth/azure-ad/oauth-logout', fn () => null)->name('login-oauth-logout');
        Route::get('auth/websso/logout', fn () => null)->name('login-websso-logout');
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_redirects_to_login_selection_when_user_not_authenticated(): void
    {
        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login-selection'));
    }

    public function test_logs_out_local_user_and_redirects_to_login_selection(): void
    {
        $user = User::factory()->affiliate()->create();

        $user = User::find($user->id);

        $this->actingAs($user);

        $oldSessionId = session()->getId();

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login-selection'));
        $this->assertGuest();

        $this->assertNotEquals($oldSessionId, session()->getId());
    }

    public function test_redirects_sso_user_to_entra_logout_by_default(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login-oauth-logout'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_redirects_sso_user_to_websso_logout_when_api_key_set(): void
    {
        config(['nusoa.sso.apigeeApiKey' => 'test-api-key']);

        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login-websso-logout'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_redirects_sso_user_to_websso_logout_when_forgerock_direct(): void
    {
        config(['nusoa.sso.strategy' => 'forgerock-direct']);

        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login-websso-logout'));
        $this->assertAuthenticatedAs($user);
    }
}
