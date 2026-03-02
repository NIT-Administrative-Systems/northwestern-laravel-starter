<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Http\Controllers\LoginSelectionController;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LoginSelectionController::class)]
class LoginSelectionControllerTest extends TestCase
{
    public function test_renders_view_with_entra_route_when_local_auth_enabled(): void
    {
        config(['local-auth.enabled' => true]);

        $response = $this->get(route('login-selection'));

        $response->assertOk();
        $response->assertViewIs('auth.login-selection');
        $response->assertViewHas('ssoRoute', 'login-oauth-redirect');
    }

    public function test_renders_view_with_websso_route_when_local_auth_enabled_and_api_key_set(): void
    {
        config([
            'local-auth.enabled' => true,
            'nusoa.sso.apigeeApiKey' => 'test-api-key',
        ]);

        $response = $this->get(route('login-selection'));

        $response->assertOk();
        $response->assertViewIs('auth.login-selection');
        $response->assertViewHas('ssoRoute', 'login-websso');
    }

    public function test_renders_view_with_websso_route_when_local_auth_enabled_and_forgerock_direct(): void
    {
        config([
            'local-auth.enabled' => true,
            'nusoa.sso.strategy' => 'forgerock-direct',
        ]);

        $response = $this->get(route('login-selection'));

        $response->assertOk();
        $response->assertViewIs('auth.login-selection');
        $response->assertViewHas('ssoRoute', 'login-websso');
    }

    public function test_redirects_to_entra_when_local_auth_disabled(): void
    {
        config(['local-auth.enabled' => false]);

        $response = $this->get(route('login-selection'));

        $response->assertRedirect(route('login-oauth-redirect'));
    }

    public function test_redirects_to_websso_when_local_auth_disabled_and_api_key_set(): void
    {
        config([
            'local-auth.enabled' => false,
            'nusoa.sso.apigeeApiKey' => 'test-api-key',
        ]);

        $response = $this->get(route('login-selection'));

        $response->assertRedirect(route('login-websso'));
    }

    public function test_redirects_to_websso_when_local_auth_disabled_and_forgerock_direct(): void
    {
        config([
            'local-auth.enabled' => false,
            'nusoa.sso.strategy' => 'forgerock-direct',
        ]);

        $response = $this->get(route('login-selection'));

        $response->assertRedirect(route('login-websso'));
    }

    public function test_renders_view_in_ci_env_when_local_auth_disabled(): void
    {
        config(['local-auth.enabled' => false]);

        $this->app->detectEnvironment(fn () => 'ci');

        $response = $this->get(route('login-selection'));

        $response->assertOk();
        $response->assertViewIs('auth.login-selection');
    }

    public function test_renders_view_with_websso_route_in_ci_env_when_api_key_set(): void
    {
        config([
            'local-auth.enabled' => false,
            'nusoa.sso.apigeeApiKey' => 'test-api-key',
        ]);

        $this->app->detectEnvironment(fn () => 'ci');

        $response = $this->get(route('login-selection'));

        $response->assertOk();
        $response->assertViewIs('auth.login-selection');
        $response->assertViewHas('ssoRoute', 'login-websso');
    }
}
