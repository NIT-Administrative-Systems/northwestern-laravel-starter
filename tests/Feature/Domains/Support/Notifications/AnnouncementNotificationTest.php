<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Support\Notifications;

use App\Domains\Support\Enums\AnnouncementSeverity;
use App\Domains\Support\Models\Announcement;
use App\Domains\Support\Notifications\AnnouncementNotification;
use App\Domains\User\Data\UserPreferences;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AnnouncementNotification::class)]
final class AnnouncementNotificationTest extends TestCase
{
    public function test_it_is_emailed_unless_the_person_turned_announcement_emails_off(): void
    {
        $notification = new AnnouncementNotification(Announcement::factory()->create());

        $this->assertSame(['database', 'mail'], $notification->via(User::factory()->create(['email' => 'willie@example.edu'])));
        $this->assertSame(['database'], $notification->via(User::factory()->create(['preferences' => new UserPreferences(emailAnnouncements: false)])));
        $this->assertSame(['database'], $notification->via(User::factory()->create(['email' => null])));
    }

    public function test_the_bell_and_the_email_link_to_the_announcement(): void
    {
        $announcement = Announcement::factory()->severity(AnnouncementSeverity::Warning)->create([
            'title' => 'Scheduled maintenance',
            'body' => 'The application is **unavailable** Saturday.',
        ]);
        $user = User::factory()->create(['first_name' => 'Willie']);
        $notification = new AnnouncementNotification($announcement);
        $url = url("/app/announcements#announcement-{$announcement->id}");

        $database = $notification->toDatabase($user);
        $this->assertSame('Scheduled maintenance', $database['title']);
        $this->assertSame('The application is unavailable Saturday.', trim((string) $database['body']));
        $this->assertSame($url, $database['actions'][0]['url']);

        $mail = $notification->toMail($user);
        $this->assertSame('Scheduled maintenance', $mail->subject);
        $this->assertSame('Hello Willie,', $mail->greeting);
        $this->assertSame($url, $mail->actionUrl);
    }
}
