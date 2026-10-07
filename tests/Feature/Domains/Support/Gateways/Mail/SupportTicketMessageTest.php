<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Support\Gateways\Mail;

use App\Domains\Support\Gateways\Mail\SupportTicketMessage;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SupportTicketMessage::class)]
final class SupportTicketMessageTest extends TestCase
{
    public function test_build_sets_subject_markdown_and_view_data_for_primary_email(): void
    {
        config(['app.env' => 'testing']);

        $user = User::factory()->affiliate()->create([
            'first_name' => 'Alex',
            'last_name' => 'Rivera',
            'email' => 'alex@example.com',
            'username' => 'arivera',
            'departments' => ['IT'],
        ]);

        $ticket = SupportTicket::factory()->for($user)->pending()->create([
            'subject' => 'Access request',
            'details' => 'Need elevated permissions.',
        ]);

        $mailable = new SupportTicketMessage($ticket, 'SUP-303', false)->build();

        $this->assertSame('[SUP-303 - Testing] Access request', $mailable->subject);
        $this->assertSame('mail.support.ticket-message', $mailable->markdown);
        $this->assertSame('SUP-303', $mailable->viewData['referenceNumber']);
        $this->assertSame('Access request', $mailable->viewData['subject']);
        $this->assertStringContainsString('Need elevated permissions.', (string) $mailable->viewData['details']);
        $this->assertSame('Alex Rivera', $mailable->viewData['submitterName']);
        $this->assertSame('alex@example.com', $mailable->viewData['submitterEmail']);
        $this->assertSame('arivera', $mailable->viewData['submitterUsername']);
        $this->assertSame(['IT'], $mailable->viewData['submitterDepartments']);
        $this->assertSame('Affiliate', $mailable->viewData['submitterAffiliation']);
        $this->assertSame('Testing', $mailable->viewData['environment']);
        $this->assertFalse($mailable->viewData['fallbackMode']);
    }

    public function test_build_sets_fallback_mode_in_view_data_when_fallback_email_is_sent(): void
    {
        $user = User::factory()->affiliate()->create();
        $ticket = SupportTicket::factory()->for($user)->pending()->create();

        $mailable = new SupportTicketMessage($ticket, 'SUP-404', true)->build();

        $this->assertTrue($mailable->viewData['fallbackMode']);
    }

    // People type plain text: neither HTML nor Markdown in it should render in the team's email.
    public function test_details_render_as_the_text_that_was_typed(): void
    {
        $ticket = SupportTicket::factory()->for(User::factory()->create())->pending()->create([
            'subject' => 'Help',
            'details' => "<a href=\"https://phish.example.com\">Open your ticket</a>\n[Sign in](https://phish.example.com) <img src=\"https://track.example.com/p.gif\">",
        ]);

        $html = new SupportTicketMessage($ticket, 'SUP-404', false)->render();

        $this->assertStringNotContainsString('<a href="https://phish.example.com', $html);
        $this->assertStringNotContainsString('<img src="https://track.example.com', $html);
        $this->assertStringContainsString('&lt;a href=', $html);
        $this->assertStringContainsString('[Sign in](https://phish.example.com)', $html);
    }
}
