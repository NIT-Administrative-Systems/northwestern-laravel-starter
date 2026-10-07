<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Domains\Support\Models\Announcement;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * The announcement banner for signed-out visitors, in resources/views/components.
 */
#[CoversNothing]
final class PublicAnnouncementBannerTest extends TestCase
{
    public function test_public_pages_and_sign_in_show_announcements_for_signed_out_visitors(): void
    {
        Announcement::factory()->public()->create(['title' => 'Sign-in changes on October 12']);
        Announcement::factory()->create(['title' => 'Only for people signed in']);

        $this->get('/')->assertOk()->assertSee('Sign-in changes on October 12')->assertDontSee('Only for people signed in')->assertDontSee('Read More');
        $this->get('/app/login')->assertOk()->assertSee('Sign-in changes on October 12');
    }

    // Error pages must work when the database is the problem, and need no extra noise.
    public function test_error_pages_show_no_announcements(): void
    {
        Announcement::factory()->public()->create(['title' => 'Sign-in changes on October 12']);

        $this->get('/this-page-does-not-exist')->assertNotFound()->assertDontSee('Sign-in changes on October 12');
    }
}
