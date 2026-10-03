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

    public function test_the_header_shows_the_environment_badge_outside_production(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('Environment: Testing');
    }

    public function test_the_help_menu_lists_the_changelog_and_the_documentation_link_when_set(): void
    {
        config(['support.enabled' => true, 'support.documentation_url' => null]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-cy="help-menu-trigger"', escape: false)
            ->assertSee(route('support.changelog.index'), escape: false)
            ->assertDontSee('Contact Support')
            ->assertDontSee('https://docs.example.edu/', escape: false);

        config(['support.documentation_url' => 'https://docs.example.edu/']);

        $this->get(route('home'))->assertSee('https://docs.example.edu/', escape: false);
    }
}
