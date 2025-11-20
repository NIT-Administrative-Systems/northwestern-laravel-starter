<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Domains\User\Models\User;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(HomeController::class)]
class HomeControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/', HomeController::class)->name('home');
    }

    public function test_redirects_guest_users_to_login(): void
    {
        $response = $this->get(route('home'));

        $response->assertRedirectToRoute('login-selection');
    }

    public function test_renders_default_home_view_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertViewIs('default-home');
    }
}
