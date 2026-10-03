<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\App\Pages;

use App\Domains\User\Models\User;
use App\Filament\App\Pages\Dashboard;
use App\Providers\Filament\AppPanelProvider;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Dashboard::class)]
final class DashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AppPanelProvider::ID);
    }

    public function test_the_app_panel_uses_the_starter_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/app')
            ->assertOk()
            ->assertSee('Starter placeholder')
            ->assertSee('app/Filament/App/Pages/Dashboard.php')
            ->assertSee('Getting started');
    }

    public function test_the_checklist_ticks_what_it_can_detect(): void
    {
        $this->actingAs(User::factory()->create());

        config(['northwestern-filament-theme.unit.name' => null, 'sentry.dsn' => null]);

        Livewire::test(Dashboard::class)
            ->assertSeeHtml('data-cy="starter-step-unit"')
            ->assertDontSeeHtml('title="Done"');

        config(['northwestern-filament-theme.unit.name' => 'Information Technology']);

        Livewire::test(Dashboard::class)->assertSeeHtml('Done');
    }

    public function test_the_gallery_link_is_hidden_in_production(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Dashboard::class)->assertSee('Component gallery');

        $this->app->detectEnvironment(fn () => 'production');

        Livewire::test(Dashboard::class)->assertDontSee('Component gallery');
    }
}
