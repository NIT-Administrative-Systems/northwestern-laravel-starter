<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Domains\User\Models\User;
use App\Http\Controllers\Auth\LogoutSelectionController;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LogoutSelectionController::class)]
class LogoutSelectionControllerTest extends TestCase
{
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

    public function test_redirects_oauth_user_to_oauth_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login-oauth-logout'));
        $this->assertAuthenticatedAs($user);
    }
}
