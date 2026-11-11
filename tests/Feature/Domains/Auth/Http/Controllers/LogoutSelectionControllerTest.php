<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Http\Controllers\LogoutSelectionController;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LogoutSelectionController::class)]
final class LogoutSelectionControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('login-oauth-logout')) {
            Route::get('auth/azure-ad/oauth-logout', fn () => null)->name('login-oauth-logout');
        }

        if (! Route::has('login-websso-logout')) {
            Route::get('auth/websso/logout', fn () => null)->name('login-websso-logout');
        }

        Route::getRoutes()->refreshNameLookups();
    }

    public function test_signing_out_with_nobody_signed_in_goes_to_sign_in(): void
    {
        $response = $this->post(route('logout'));

        $response->assertRedirect(route('filament.app.auth.login'));
    }

    public function test_a_local_account_signs_out_here_and_goes_to_sign_in(): void
    {
        $user = User::factory()->affiliate()->create();

        $user = User::find($user->id);

        $this->actingAs($user);

        $oldSessionId = session()->getId();

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('filament.app.auth.login'));
        $this->assertGuest();

        $this->assertNotEquals($oldSessionId, session()->getId());
    }

    public function test_redirects_sso_user_to_entra_logout_by_default(): void
    {
        config([
            'services.northwestern-azure.client_id' => 'test-client-id',
            'services.northwestern-azure.client_secret' => 'test-client-secret',
        ]);

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

    public function test_logs_out_sso_user_locally_when_no_sso_provider_configured(): void
    {
        config([
            'nusoa.sso.apigeeApiKey' => null,
            'nusoa.sso.strategy' => null,
            'services.northwestern-azure.client_id' => null,
            'services.northwestern-azure.client_secret' => null,
        ]);

        $user = User::factory()->create();
        $user = User::find($user->id);

        $this->actingAs($user);

        $oldSessionId = session()->getId();

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('filament.app.auth.login'));
        $this->assertGuest();

        $this->assertNotEquals($oldSessionId, session()->getId());
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
