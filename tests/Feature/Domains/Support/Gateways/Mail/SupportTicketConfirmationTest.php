<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Support\Gateways\Mail;

use App\Domains\Core\Formatting\NorthwesternDateTime;
use App\Domains\Support\Gateways\Mail\SupportTicketConfirmation;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SupportTicketConfirmation::class)]
final class SupportTicketConfirmationTest extends TestCase
{
    public function test_build_sets_subject_markdown_and_view_data(): void
    {
        $user = User::factory()->affiliate()->create([
            'first_name' => 'Pat',
        ]);

        $ticket = SupportTicket::factory()->for($user)->pending()->create([
            'subject' => 'Login issue',
            'details' => 'I cannot sign in.',
        ]);

        $mailable = new SupportTicketConfirmation($ticket, 'SUP-101')->build();

        $this->assertSame('We received your support request: Login issue', $mailable->subject);
        $this->assertSame('mail.support.ticket-confirmation', $mailable->markdown);
        $this->assertSame('Pat', $mailable->viewData['submitter']);
        $this->assertSame('Login issue', $mailable->viewData['subject']);
        $this->assertSame('SUP-101', $mailable->viewData['referenceNumber']);
        $this->assertStringContainsString('I cannot sign in.', (string) $mailable->viewData['details']);
        $this->assertSame(
            NorthwesternDateTime::format($ticket->created_at, $user->timezone),
            $mailable->viewData['submittedAt'],
        );
    }

    public function test_the_email_does_not_promise_a_channel_for_the_follow_up(): void
    {
        $ticket = SupportTicket::factory()->for(User::factory()->affiliate()->create())->pending()->create();

        $html = new SupportTicketConfirmation($ticket, 'SUP-303')->render();

        $this->assertStringContainsString('someone will follow up with you as soon as possible', $html);
        $this->assertStringNotContainsString('by email', $html);
    }

    public function test_build_greets_by_full_name_when_first_name_is_missing(): void
    {
        $user = User::factory()->affiliate()->create([
            'first_name' => null,
        ]);

        $ticket = SupportTicket::factory()->for($user)->pending()->create();

        $mailable = new SupportTicketConfirmation($ticket, 'SUP-202')->build();

        $this->assertSame($user->full_name, $mailable->viewData['submitter']);
    }
}
