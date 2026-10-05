<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Support\Models;

use App\Domains\Auth\Enums\SystemRole;
use App\Domains\Auth\Models\Role;
use App\Domains\Support\Enums\AnnouncementSeverity;
use App\Domains\Support\Enums\AnnouncementStatus;
use App\Domains\Support\Models\Announcement;
use App\Domains\Support\Models\AnnouncementDismissal;
use App\Domains\User\Enums\Affiliation;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Announcement::class)]
final class AnnouncementTest extends TestCase
{
    public function test_its_status_follows_its_dates(): void
    {
        $this->assertSame(AnnouncementStatus::Draft, Announcement::factory()->draft()->create()->status);
        $this->assertSame(AnnouncementStatus::Scheduled, Announcement::factory()->scheduled()->create()->status);
        $this->assertSame(AnnouncementStatus::Live, Announcement::factory()->create()->status);
        $this->assertSame(AnnouncementStatus::Ended, Announcement::factory()->ended()->create()->status);
    }

    // An enum is an object, which Eloquent would otherwise cache from the first read.
    public function test_its_status_reflects_changes_after_it_was_read(): void
    {
        $announcement = Announcement::factory()->draft()->create();
        $this->assertSame(AnnouncementStatus::Draft, $announcement->status);

        $announcement->forceFill(['published_at' => now(), 'starts_at' => now()]);

        $this->assertSame(AnnouncementStatus::Live, $announcement->status);
    }

    public function test_only_started_announcements_that_havent_ended_are_live(): void
    {
        $live = Announcement::factory()->create();
        $endingLater = Announcement::factory()->create(['ends_at' => now()->addDay()]);
        Announcement::factory()->draft()->create();
        Announcement::factory()->scheduled()->create();
        $ended = Announcement::factory()->ended()->create();

        $this->assertEqualsCanonicalizing([$live->id, $endingLater->id], Announcement::query()->live()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$live->id, $endingLater->id, $ended->id], Announcement::query()->started()->pluck('id')->all());
    }

    public function test_an_audience_of_roles_and_affiliations_matches_people_with_any_of_them(): void
    {
        $coordinators = Role::factory()->create(['name' => 'Coordinators']);
        $forCoordinators = Announcement::factory()->targeted(roles: [$coordinators])->create();
        $forStaff = Announcement::factory()->targeted(affiliations: [Affiliation::Staff])->create();
        $forEveryone = Announcement::factory()->create();
        $forVisitors = Announcement::factory()->public()->create();
        Announcement::factory()->targeted()->create(); // Names nobody, so reaches nobody.

        $coordinator = User::factory()->affiliate()->create();
        $coordinator->roles()->attach($coordinators);
        $staff = User::factory()->staff()->create();

        $this->assertEqualsCanonicalizing([$forCoordinators->id, $forEveryone->id, $forVisitors->id], Announcement::query()->visibleTo($coordinator)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$forStaff->id, $forEveryone->id, $forVisitors->id], Announcement::query()->visibleTo($staff)->pluck('id')->all());
        $this->assertSame([$forVisitors->id], Announcement::query()->visibleTo(null)->pluck('id')->all());
    }

    public function test_super_administrators_see_every_announcement(): void
    {
        Announcement::factory()->targeted(affiliations: [Affiliation::Student])->create();
        Announcement::factory()->create();
        $superAdmin = User::factory()->create();
        $superAdmin->roles()->attach(Role::query()->where('name', SystemRole::SuperAdministrator)->sole());

        $this->assertSame(2, Announcement::query()->visibleTo($superAdmin)->count());
    }

    public function test_announcements_are_ordered_by_severity_then_newest_start(): void
    {
        $olderWarning = Announcement::factory()->severity(AnnouncementSeverity::Warning)->create(['starts_at' => now()->subDays(2)]);
        $info = Announcement::factory()->create();
        $critical = Announcement::factory()->severity(AnnouncementSeverity::Critical)->create(['starts_at' => now()->subWeek()]);
        $newerWarning = Announcement::factory()->severity(AnnouncementSeverity::Warning)->create(['starts_at' => now()->subDay()]);

        $this->assertSame([$critical->id, $newerWarning->id, $olderWarning->id, $info->id], Announcement::query()->byImportance()->pluck('id')->all());
    }

    public function test_dismissed_announcements_are_left_out_for_that_person_only(): void
    {
        $announcement = Announcement::factory()->create();
        [$dismissed, $other] = User::factory()->count(2)->create();
        AnnouncementDismissal::query()->create(['announcement_id' => $announcement->id, 'user_id' => $dismissed->id, 'dismissed_at' => now()]);

        $this->assertFalse(Announcement::query()->notDismissedBy($dismissed)->exists());
        $this->assertTrue(Announcement::query()->notDismissedBy($other)->exists());
    }

    // Authors write Markdown, never HTML or script links.
    public function test_the_body_renders_markdown_without_raw_html_or_unsafe_links(): void
    {
        $announcement = Announcement::factory()->make(['body' => '**Bold** <script>alert(1)</script> [safe](https://example.edu) [bad](javascript:alert(1))']);

        $html = (string) $announcement->bodyHtml();

        $this->assertStringContainsString('<strong>Bold</strong>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('href="https://example.edu"', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_it_summarises_its_audience(): void
    {
        $role = Role::factory()->create(['name' => 'Coordinators']);

        $this->assertSame('Everyone signed in', Announcement::factory()->make()->audienceSummary());
        $this->assertSame('Coordinators, Staff', Announcement::factory()->targeted([$role], [Affiliation::Staff])->make()->audienceSummary());
        $this->assertSame('Nobody', Announcement::factory()->targeted()->make()->audienceSummary());
    }

    public function test_deleting_it_deletes_its_dismissals(): void
    {
        $announcement = Announcement::factory()->create();
        AnnouncementDismissal::query()->create(['announcement_id' => $announcement->id, 'user_id' => User::factory()->create()->id, 'dismissed_at' => now()]);

        $announcement->delete();

        $this->assertSame(0, AnnouncementDismissal::query()->count());
    }
}
