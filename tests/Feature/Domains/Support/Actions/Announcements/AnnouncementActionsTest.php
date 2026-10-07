<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Support\Actions\Announcements;

use App\Domains\Support\Actions\Announcements\DismissAnnouncement;
use App\Domains\Support\Actions\Announcements\DuplicateAnnouncement;
use App\Domains\Support\Actions\Announcements\EndAnnouncement;
use App\Domains\Support\Actions\Announcements\PublishAnnouncement;
use App\Domains\Support\Enums\AnnouncementSeverity;
use App\Domains\Support\Enums\AnnouncementStatus;
use App\Domains\Support\Jobs\NotifyAnnouncementAudience;
use App\Domains\Support\Models\Announcement;
use App\Domains\Support\Models\AnnouncementDismissal;
use App\Domains\User\Enums\Affiliation;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(PublishAnnouncement::class)]
#[CoversClass(EndAnnouncement::class)]
#[CoversClass(DuplicateAnnouncement::class)]
#[CoversClass(DismissAnnouncement::class)]
final class AnnouncementActionsTest extends TestCase
{
    public function test_publishing_now_with_notify_sends_at_once(): void
    {
        Queue::fake();
        $announcement = Announcement::factory()->draft()->create();

        resolve(PublishAnnouncement::class)($announcement, now(), now()->addWeek(), true);

        $announcement->refresh();
        $this->assertSame(AnnouncementStatus::Live, $announcement->status);
        $this->assertTrue($announcement->notify_audience);
        Queue::assertPushed(NotifyAnnouncementAudience::class, fn (NotifyAnnouncementAudience $job): bool => $job->announcement->is($announcement));
    }

    // announcements:notify sends once it starts.
    public function test_a_scheduled_announcement_isnt_notified_until_it_starts(): void
    {
        Queue::fake();
        $announcement = Announcement::factory()->draft()->create();

        resolve(PublishAnnouncement::class)($announcement, now()->addDay(), null, true);

        $this->assertSame(AnnouncementStatus::Scheduled, $announcement->fresh()?->status);
        Queue::assertNothingPushed();
    }

    public function test_only_a_draft_can_be_published_and_it_must_end_after_it_starts(): void
    {
        $publish = resolve(PublishAnnouncement::class);

        $this->assertThrows(fn () => $publish(Announcement::factory()->create(), now(), null, false), InvalidArgumentException::class);
        $this->assertThrows(fn () => $publish(Announcement::factory()->draft()->create(), now(), now()->subMinute(), false), InvalidArgumentException::class);
    }

    public function test_ending_a_live_announcement_ends_it_now(): void
    {
        $announcement = Announcement::factory()->create();

        resolve(EndAnnouncement::class)($announcement);

        $this->assertSame(AnnouncementStatus::Ended, $announcement->fresh()?->status);
        $this->assertThrows(fn () => resolve(EndAnnouncement::class)(Announcement::factory()->draft()->create()), InvalidArgumentException::class);
    }

    public function test_duplicating_copies_content_and_audience_into_a_new_draft(): void
    {
        $original = Announcement::factory()->targeted(affiliations: [Affiliation::Staff])->ended()->create(['title' => 'Registration is open', 'notify_audience' => true, 'notified_at' => now()]);
        $author = User::factory()->create();

        $copy = resolve(DuplicateAnnouncement::class)($original, $author);

        $this->assertSame(AnnouncementStatus::Draft, $copy->status);
        $this->assertSame('Registration is open', $copy->title);
        $this->assertSame(['staff'], $copy->affiliations);
        $this->assertFalse((bool) $copy->notify_audience);
        $this->assertNull($copy->notified_at);
        $this->assertSame($author->id, $copy->created_by_user_id);
    }

    public function test_a_person_dismisses_a_live_announcement_once(): void
    {
        $announcement = Announcement::factory()->create();
        $user = User::factory()->create();

        resolve(DismissAnnouncement::class)($announcement, $user);
        resolve(DismissAnnouncement::class)($announcement, $user);

        $this->assertSame(1, AnnouncementDismissal::query()->where('user_id', $user->id)->count());
    }

    public function test_critical_announcements_and_ones_the_person_cant_see_cant_be_dismissed(): void
    {
        $user = User::factory()->staff()->create();
        $dismiss = resolve(DismissAnnouncement::class);

        $this->assertThrows(fn () => $dismiss(Announcement::factory()->severity(AnnouncementSeverity::Critical)->create(), $user), AuthorizationException::class);
        $this->assertThrows(fn () => $dismiss(Announcement::factory()->targeted(affiliations: [Affiliation::Student])->create(), $user), AuthorizationException::class);
        $this->assertThrows(fn () => $dismiss(Announcement::factory()->ended()->create(), $user), AuthorizationException::class);
        $this->assertSame(0, AnnouncementDismissal::query()->count());
    }

    public function test_it_is_refused_while_impersonating(): void
    {
        $impersonate = Mockery::mock();
        $impersonate->shouldReceive('isImpersonating')->andReturn(true);
        $impersonate->shouldReceive('getImpersonatorId')->andReturn(null);
        $this->app->instance('impersonate', $impersonate);

        $this->expectException(AuthorizationException::class);

        resolve(DismissAnnouncement::class)(Announcement::factory()->create(), User::factory()->create());
    }
}
