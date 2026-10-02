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
}
