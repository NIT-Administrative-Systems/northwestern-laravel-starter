<?php

declare(strict_types=1);

namespace Tests\Feature\Providers\Filament;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Models\User;
use App\Providers\Filament\AdministrationPanelProvider;
use App\Providers\Filament\AppPanelProvider;
use Filament\Facades\Filament;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AppPanelProvider::class)]
final class AppPanelProviderTest extends TestCase
{
    public function test_app_is_the_default_panel(): void
    {
        $this->assertSame(AppPanelProvider::ID, Filament::getDefaultPanel()->getId());
    }

    public function test_every_signed_in_user_can_access_the_app_panel(): void
    {
        $user = User::factory()->affiliate()->create();

        $this->assertTrue($user->canAccessPanel(Filament::getPanel(AppPanelProvider::ID)));
        $this->assertFalse($user->canAccessPanel(Filament::getPanel(AdministrationPanelProvider::ID)));

        $this->actingAs($user)->get('/app')->assertOk();
    }

    public function test_user_menu_links_to_administration_only_for_users_who_can_access_it(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/app')
            ->assertOk()
            ->assertDontSee('data-cy="admin-panel-link"', escape: false);

        $admin = User::factory()->create();
        $admin->givePermissionTo(SystemPermission::AccessAdministrationPanel);

        $this->actingAs($admin)
            ->get('/app')
            ->assertOk()
            ->assertSee('data-cy="admin-panel-link"', escape: false);
    }

    public function test_top_bar_shows_the_app_name_and_the_help_menu(): void
    {
        config(['support.enabled' => true]);

        $this->actingAs(User::factory()->create())
            ->get('/app')
            ->assertOk()
            ->assertSee(config('app.name'))
            ->assertSee('data-cy="help-menu-trigger"', escape: false)
            ->assertSee(route('support.changelog.index'), escape: false)
            ->assertSee('Contact Support');
    }

    public function test_app_panel_has_the_footer(): void
    {
        $this->actingAs(User::factory()->create())->get('/app')->assertOk()->assertSee('Privacy Statement');
    }

    // Its own test: the footer's render hook is registered when a panel boots, so requesting
    // /app first in the same application would leave the app panel's hook in place.
    public function test_administration_panel_has_no_footer(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(SystemPermission::AccessAdministrationPanel);

        $this->actingAs($admin)->get('/administration')->assertOk()->assertDontSee('Privacy Statement');
    }
}
