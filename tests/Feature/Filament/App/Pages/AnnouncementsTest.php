<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\App\Pages;

use App\Domains\Support\Models\Announcement;
use App\Domains\Support\Models\AnnouncementDismissal;
use App\Domains\User\Enums\Affiliation;
use App\Domains\User\Models\User;
use App\Filament\App\Pages\Announcements;
use App\Providers\Filament\AppPanelProvider;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class AnnouncementsTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AppPanelProvider::ID);
        $this->user = User::factory()->staff()->create();
        $this->actingAs($this->user);
    }

    public function test_it_lists_what_the_persons_audience_can_see_live_first_dismissed_or_not(): void
    {
        $ended = Announcement::factory()->ended()->create(['title' => 'Spring registration']);
        $dismissed = Announcement::factory()->create(['title' => 'New feature', 'starts_at' => now()->subDays(3)]);
        AnnouncementDismissal::query()->create(['announcement_id' => $dismissed->id, 'user_id' => $this->user->id, 'dismissed_at' => now()]);
        $live = Announcement::factory()->targeted(affiliations: [Affiliation::Staff])->create(['title' => 'Staff meeting']);
        Announcement::factory()->targeted(affiliations: [Affiliation::Student])->create(['title' => 'For students']);
        Announcement::factory()->draft()->create(['title' => 'Still a draft']);
        Announcement::factory()->scheduled()->create(['title' => 'Not started']);

        Livewire::test(Announcements::class)
            ->assertSeeInOrder(['Staff meeting', 'New feature', 'Spring registration'])
            ->assertDontSee(['For students', 'Still a draft', 'Not started'])
            ->assertSeeHtml("id=\"announcement-{$live->id}\"")
            ->assertSeeHtml("id=\"announcement-{$ended->id}\"");
    }

    public function test_it_pages_ten_at_a_time(): void
    {
        Announcement::factory()->count(Announcements::PER_PAGE + 1)->create();

        $this->assertCount(Announcements::PER_PAGE, (new Announcements())->getAnnouncements()->items());
    }

    public function test_it_says_when_there_are_none(): void
    {
        $this->get('/app/announcements')->assertOk()->assertSee('No Announcements');
    }

    public function test_the_help_menu_links_to_it_for_people_signed_in(): void
    {
        $this->get('/app')->assertOk()->assertSee(url('/app/announcements'), escape: false);

        $this->app['auth']->forgetGuards();
        $this->get('/')->assertOk()->assertDontSee(url('/app/announcements'), escape: false);
    }
}
