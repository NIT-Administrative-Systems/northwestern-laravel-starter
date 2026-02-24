<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Support\Gateways\Mail;

use App\Domains\Support\Gateways\Mail\SupportTicketConfirmation;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SupportTicketConfirmation::class)]
class SupportTicketConfirmationTest extends TestCase
{
    public function test_build_sets_subject_markdown_and_view_data(): void
    {
        $user = User::factory()->affiliate()->create([
            'first_name' => 'Pat',
        ]);

        $ticket = SupportTicket::factory()->for($user)->pending()->create([
            'subject' => 'Login issue',
        ]);

        $mailable = new SupportTicketConfirmation($ticket, 'SUP-101')->build();

        $this->assertSame('We received your support request - Login issue', $mailable->subject);
        $this->assertSame('mail.support.ticket-confirmation', $mailable->markdown);
        $this->assertSame('Pat', $mailable->viewData['submitter']);
        $this->assertSame('Login issue', $mailable->viewData['subject']);
        $this->assertSame('SUP-101', $mailable->viewData['referenceNumber']);
    }

    public function test_build_uses_default_submitter_label_when_first_name_is_missing(): void
    {
        $user = User::factory()->affiliate()->create([
            'first_name' => null,
        ]);

        $ticket = SupportTicket::factory()->for($user)->pending()->create();

        $mailable = new SupportTicketConfirmation($ticket, 'SUP-202')->build();

        $this->assertSame('User', $mailable->viewData['submitter']);
    }
}
