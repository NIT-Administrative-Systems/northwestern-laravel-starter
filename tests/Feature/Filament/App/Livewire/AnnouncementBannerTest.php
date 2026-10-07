<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\App\Livewire;

use App\Domains\Support\Enums\AnnouncementSeverity;
use App\Domains\Support\Models\Announcement;
use App\Domains\Support\Models\AnnouncementDismissal;
use App\Domains\User\Models\User;
use App\Filament\App\Livewire\AnnouncementBanner;
use App\Providers\Filament\AppPanelProvider;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class AnnouncementBannerTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AppPanelProvider::ID);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_it_shows_the_most_important_announcement_and_counts_the_others(): void
    {
        Announcement::factory()->create(['title' => 'New feature']);
        Announcement::factory()->severity(AnnouncementSeverity::Warning)->create(['title' => 'Maintenance Saturday']);

        Livewire::test(AnnouncementBanner::class)
            ->assertSee('Maintenance Saturday')
            ->assertDontSee('New feature')
            ->assertSee('1 More Announcement')
            ->assertSee('Read More')
            ->assertSeeHtml('wire:click="dismiss(');
    }

    public function test_dismissing_shows_the_next_one_and_then_nothing(): void
    {
        $warning = Announcement::factory()->severity(AnnouncementSeverity::Warning)->create(['title' => 'Maintenance Saturday']);
        $info = Announcement::factory()->create(['title' => 'New feature']);

        Livewire::test(AnnouncementBanner::class)
            ->call('dismiss', $warning->id)
            ->assertSee('New feature')
            ->assertDontSee('More Announcement')
            ->call('dismiss', $info->id)
            ->assertDontSee('New feature')
            ->assertDontSeeHtml('data-testid="announcement-banner"');

        $this->assertSame(2, AnnouncementDismissal::query()->where('user_id', $this->user->id)->count());
    }

    public function test_a_critical_announcement_has_no_dismiss_button(): void
    {
        Announcement::factory()->severity(AnnouncementSeverity::Critical)->create(['title' => 'Outage']);

        Livewire::test(AnnouncementBanner::class)
            ->assertSee('Outage')
            ->assertDontSeeHtml('data-testid="dismiss-announcement"');
    }

    public function test_an_impersonating_administrator_cant_dismiss(): void
    {
        Announcement::factory()->create(['title' => 'New feature']);
        $impersonate = Mockery::mock();
        $impersonate->shouldReceive('isImpersonating')->andReturn(true);
        $impersonate->shouldReceive('getImpersonatorId')->andReturn(null);
        $this->app->instance('impersonate', $impersonate);

        Livewire::test(AnnouncementBanner::class)
            ->assertSee('New feature')
            ->assertDontSeeHtml('data-testid="dismiss-announcement"');
    }

    public function test_it_is_on_every_app_page(): void
    {
        Announcement::factory()->create(['title' => 'Maintenance Saturday']);

        $this->get('/app')->assertOk()->assertSee('Maintenance Saturday');
        $this->get('/app/account/profile')->assertOk()->assertSee('Maintenance Saturday');
    }
}
