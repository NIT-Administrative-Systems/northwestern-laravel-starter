<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Domains\User\Models\User;
use App\Http\Controllers\HomeController;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(HomeController::class)]
final class HomeControllerTest extends TestCase
{
    public function test_guests_see_the_landing_page(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertViewIs('public.landing')
            ->assertSee(config('app.name'))
            ->assertSee('href="' . url('/app/login') . '"', escape: false);
    }

    public function test_signed_in_users_are_sent_to_the_app_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertRedirect('/app');
    }

    public function test_the_landing_page_uses_the_light_only_public_layout_with_the_footer(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<html class="fi min-h-screen"', escape: false)
            ->assertSee('data-cy="sign-in-link"', escape: false)
            ->assertSee('Privacy Statement')
            ->assertSee('Report a Concern');
    }
}
