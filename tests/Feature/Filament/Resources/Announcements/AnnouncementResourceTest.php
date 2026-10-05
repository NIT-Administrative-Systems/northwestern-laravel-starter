<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources\Announcements;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\Role;
use App\Domains\Support\Enums\AnnouncementAudience;
use App\Domains\Support\Enums\AnnouncementSeverity;
use App\Domains\Support\Enums\AnnouncementStatus;
use App\Domains\Support\Jobs\NotifyAnnouncementAudience;
use App\Domains\Support\Models\Announcement;
use App\Domains\Support\Models\AnnouncementDismissal;
use App\Domains\User\Models\User;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Filament\Resources\Announcements\Schemas\AnnouncementForm;
use App\Filament\Resources\Announcements\Tables\AnnouncementsTable;
use App\Providers\Filament\AdministrationPanelProvider;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AnnouncementResource::class)]
#[CoversClass(ListAnnouncements::class)]
#[CoversClass(CreateAnnouncement::class)]
#[CoversClass(EditAnnouncement::class)]
#[CoversClass(AnnouncementForm::class)]
#[CoversClass(AnnouncementsTable::class)]
final class AnnouncementResourceTest extends TestCase
{
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AdministrationPanelProvider::ID);

        // Date pickers read times in the author's timezone; the test's times are UTC.
        $this->manager = User::factory()->create(['timezone' => 'UTC']);
        $this->manager->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ManageAnnouncements);
        $this->actingAs($this->manager);
    }

    public function test_it_needs_manage_announcements(): void
    {
        $this->get('/administration/announcements')->assertOk();

        $administrator = User::factory()->create();
        $administrator->givePermissionTo(SystemPermission::AccessAdministrationPanel);
        $this->actingAs($administrator)->get('/administration/announcements')->assertForbidden();
    }

    public function test_a_new_announcement_is_a_draft_with_its_author(): void
    {
        Livewire::test(CreateAnnouncement::class)
            ->fillForm([
                'title' => 'Scheduled maintenance',
                'body' => 'Saturday, 6 to 8 AM.',
                'severity' => AnnouncementSeverity::Warning,
                'audience' => AnnouncementAudience::Everyone,
            ])
            ->assertSee('Scheduled maintenance')
            ->call('create')
            ->assertHasNoFormErrors();

        $announcement = Announcement::query()->sole();
        $this->assertSame(AnnouncementStatus::Draft, $announcement->status);
        $this->assertSame(AnnouncementSeverity::Warning, $announcement->severity);
        $this->assertSame($this->manager->id, $announcement->created_by_user_id);
        $this->assertNull($announcement->role_ids);
    }

    public function test_a_targeted_announcement_needs_a_role_or_an_affiliation(): void
    {
        $role = Role::factory()->create();
        $form = fn () => Livewire::test(CreateAnnouncement::class)->fillForm([
            'title' => 'For coordinators',
            'body' => 'Hello.',
            'severity' => AnnouncementSeverity::Info,
            'audience' => AnnouncementAudience::Targeted,
        ]);

        $form()->call('create')->assertHasFormErrors(['role_ids' => 'required']);
        $form()->fillForm(['role_ids' => [$role->id]])->call('create')->assertHasNoFormErrors();
        $form()->fillForm(['affiliations' => ['staff']])->call('create')->assertHasNoFormErrors();

        [$byRole, $byAffiliation] = Announcement::query()->orderBy('id')->get()->all();
        $this->assertSame([[$role->id], []], [$byRole->role_ids, $byRole->affiliations]);
        $this->assertSame([[], ['staff']], [$byAffiliation->role_ids, $byAffiliation->affiliations]);
    }

    public function test_publishing_saves_the_draft_and_can_notify_the_audience(): void
    {
        Queue::fake();
        $announcement = Announcement::factory()->draft()->create(['title' => 'Old title']);

        Livewire::test(EditAnnouncement::class, ['record' => $announcement->getRouteKey()])
            ->fillForm(['title' => 'New title'])
            ->callAction('publish', data: ['starts_at' => now()->subMinute()->toDateTimeString(), 'ends_at' => null, 'notify' => true])
            ->assertHasNoActionErrors()
            ->assertNotified('Announcement published');

        $announcement->refresh();
        $this->assertSame('New title', $announcement->title);
        $this->assertSame(AnnouncementStatus::Live, $announcement->status);
        Queue::assertPushed(NotifyAnnouncementAudience::class);
    }

    public function test_a_live_announcement_can_be_edited_shown_again_ended_and_duplicated(): void
    {
        $announcement = Announcement::factory()->create();
        AnnouncementDismissal::query()->create(['announcement_id' => $announcement->id, 'user_id' => User::factory()->create()->id, 'dismissed_at' => now()]);
        $page = fn () => Livewire::test(EditAnnouncement::class, ['record' => $announcement->getRouteKey()]);

        $page()->assertActionHidden('publish')
            ->fillForm(['title' => 'Corrected title', 'ends_at' => now()->addWeek()->toDateTimeString()])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Corrected title', $announcement->fresh()?->title);

        $page()->callAction('showAgain')->assertNotified();
        $this->assertSame(0, $announcement->dismissals()->count());

        $page()->callAction('end')->assertNotified('Announcement ended');
        $this->assertSame(AnnouncementStatus::Ended, $announcement->refresh()->status);

        $page()->callAction('duplicate')->assertNotified('Copied to a new draft');
        $this->assertSame(1, Announcement::query()->whereNull('published_at')->where('title', 'Corrected title')->count());
    }

    public function test_the_list_filters_by_status(): void
    {
        $draft = Announcement::factory()->draft()->create();
        $scheduled = Announcement::factory()->scheduled()->create();
        $live = Announcement::factory()->create();
        $ended = Announcement::factory()->ended()->create();

        $list = fn (string $status) => Livewire::test(ListAnnouncements::class)->filterTable('status', $status);

        $list('draft')->assertCanSeeTableRecords([$draft])->assertCanNotSeeTableRecords([$scheduled, $live, $ended]);
        $list('scheduled')->assertCanSeeTableRecords([$scheduled])->assertCanNotSeeTableRecords([$draft, $live, $ended]);
        $list('live')->assertCanSeeTableRecords([$live])->assertCanNotSeeTableRecords([$draft, $scheduled, $ended]);
        $list('ended')->assertCanSeeTableRecords([$ended])->assertCanNotSeeTableRecords([$draft, $scheduled, $live]);
    }

    public function test_the_preview_shows_the_banner_as_written(): void
    {
        Livewire::test(CreateAnnouncement::class)
            ->assertSee('Your title')
            ->fillForm(['title' => 'Outage tonight', 'body' => 'Back by **midnight**.', 'severity' => AnnouncementSeverity::Critical])
            ->assertSee('Outage tonight')
            ->assertSeeHtml('<strong>midnight</strong>')
            ->assertSee('People can\'t dismiss a critical announcement.', escape: false);
    }
}
