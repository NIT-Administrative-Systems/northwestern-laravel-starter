<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Support\Jobs;

use App\Console\Commands\NotifyAnnouncementAudiencesCommand;
use App\Domains\Auth\Models\Role;
use App\Domains\Support\Jobs\NotifyAnnouncementAudience;
use App\Domains\Support\Models\Announcement;
use App\Domains\Support\Notifications\AnnouncementNotification;
use App\Domains\User\Enums\Affiliation;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(NotifyAnnouncementAudience::class)]
#[CoversClass(NotifyAnnouncementAudiencesCommand::class)]
#[CoversClass(Announcement::class)]
final class NotifyAnnouncementAudienceTest extends TestCase
{
    public function test_it_notifies_everyone_who_can_use_the_app_once(): void
    {
        Notification::fake();
        $person = User::factory()->create();
        User::factory()->api()->create();
        User::factory()->create(['netid_inactive' => true]);
        $announcement = Announcement::factory()->create(['notify_audience' => true]);

        (new NotifyAnnouncementAudience($announcement))->handle();
        (new NotifyAnnouncementAudience($announcement))->handle();

        Notification::assertSentTo($person, AnnouncementNotification::class);
        Notification::assertCount(1);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $announcement->fresh()?->notified_at);
    }

    public function test_a_targeted_announcement_notifies_only_its_audience(): void
    {
        Notification::fake();
        $coordinators = Role::factory()->create();
        $coordinator = User::factory()->affiliate()->create();
        $coordinator->roles()->attach($coordinators);
        $student = User::factory()->create(['primary_affiliation' => Affiliation::Student]);
        $staff = User::factory()->staff()->create();
        $announcement = Announcement::factory()->targeted([$coordinators], [Affiliation::Student])->create(['notify_audience' => true]);

        (new NotifyAnnouncementAudience($announcement))->handle();

        Notification::assertSentTo([$coordinator, $student], AnnouncementNotification::class);
        Notification::assertNotSentTo($staff, AnnouncementNotification::class);
    }

    // The banner asks which announcements a person sees (Announcement::visibleTo); this job asks which people an
    // announcement is for. The two queries run in opposite directions, so this pins their matching rule together.
    public function test_it_notifies_exactly_the_people_who_see_the_announcement(): void
    {
        [$coordinators, $advisors] = Role::factory()->count(2)->create()->all();

        $people = new Collection([
            User::factory()->affiliate()->create(),
            User::factory()->affiliate()->create(['primary_affiliation' => Affiliation::Staff]),
            User::factory()->affiliate()->create(['primary_affiliation' => Affiliation::Student]),
            tap(User::factory()->affiliate()->create(), fn (User $user) => $user->roles()->attach($coordinators)),
            tap(User::factory()->affiliate()->create(['primary_affiliation' => Affiliation::Student]), fn (User $user) => $user->roles()->attach($advisors)),
        ]);

        $announcements = [
            Announcement::factory()->create(),
            Announcement::factory()->targeted([$coordinators])->create(),
            Announcement::factory()->targeted([], [Affiliation::Staff])->create(),
            Announcement::factory()->targeted([$advisors], [Affiliation::Staff])->create(),
            Announcement::factory()->targeted()->create(),
        ];

        foreach ($announcements as $announcement) {
            $notified = NotifyAnnouncementAudience::audience($announcement)->whereKey($people->modelKeys())->pluck('id')->sort()->values()->all();
            $seeing = $people->filter(fn (User $person): bool => Announcement::query()->visibleTo($person->fresh())->whereKey($announcement->getKey())->exists())->modelKeys();
            sort($seeing);

            $this->assertSame($seeing, $notified, "Audience mismatch for announcement {$announcement->getKey()}.");
        }
    }

    public function test_it_sends_nothing_unless_the_author_asked(): void
    {
        Notification::fake();
        User::factory()->create();

        (new NotifyAnnouncementAudience(Announcement::factory()->create()))->handle();

        Notification::assertNothingSent();
    }

    // Publishing notifies straight away; the command catches announcements scheduled for later.
    public function test_the_command_queues_started_announcements_not_yet_notified(): void
    {
        Queue::fake();
        $started = Announcement::factory()->create(['notify_audience' => true]);
        Announcement::factory()->create(['notify_audience' => true, 'notified_at' => now()]);
        Announcement::factory()->scheduled()->create(['notify_audience' => true]);
        Announcement::factory()->create();

        $this->artisan(NotifyAnnouncementAudiencesCommand::class)
            ->expectsOutputToContain('Queued notifications for 1 announcement(s).')
            ->assertSuccessful();

        Queue::assertPushed(NotifyAnnouncementAudience::class, fn (NotifyAnnouncementAudience $job): bool => $job->announcement->is($started));
        Queue::assertPushed(NotifyAnnouncementAudience::class, 1);
    }
}
