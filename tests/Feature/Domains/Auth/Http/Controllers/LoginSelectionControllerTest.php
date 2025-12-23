<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Http\Controllers\LoginSelectionController;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LoginSelectionController::class)]
class LoginSelectionControllerTest extends TestCase
{
    public function test_renders_login_selection_view_when_local_auth_enabled(): void
    {
        config(['auth.local.enabled' => true]);

        $response = $this->get(route('login-selection'));

        $response->assertOk();
        $response->assertViewIs('auth.login-selection');
    }

    public function test_redirects_to_oauth_when_local_auth_disabled_and_not_ci_env(): void
    {
        config(['auth.local.enabled' => false]);

        $response = $this->get(route('login-selection'));

        $response->assertRedirect(route('login-oauth-redirect'));
    }

    public function test_renders_login_selection_view_when_local_auth_disabled_but_env_is_ci(): void
    {
        config(['auth.local.enabled' => false]);

        $this->app->detectEnvironment(fn () => 'ci');

        $response = $this->get(route('login-selection'));

        $response->assertOk();
        $response->assertViewIs('auth.login-selection');
    }
}
