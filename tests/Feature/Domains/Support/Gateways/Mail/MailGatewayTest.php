<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Support\Gateways\Mail;

use App\Domains\Support\Enums\TicketSystem;
use App\Domains\Support\Gateways\Mail\MailGateway;
use App\Domains\Support\Gateways\Mail\SupportTicketConfirmation;
use App\Domains\Support\Gateways\Mail\SupportTicketMessage;
use App\Domains\Support\Models\SupportTicket;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(MailGateway::class)]
final class MailGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['support.mail.to' => 'support@northwestern.edu']);
        config(['support.mail.reference_prefix' => 'TEST']);
    }

    public function test_create_queues_team_notification_and_user_confirmation(): void
    {
        Mail::fake();

        $ticket = SupportTicket::factory()->pending()->create();
        $gateway = new MailGateway();

        $gateway->create($ticket);

        Mail::assertQueued(SupportTicketMessage::class, function (SupportTicketMessage $mailable) {
            return $mailable->hasTo('support@northwestern.edu');
        });

        Mail::assertQueued(SupportTicketConfirmation::class, function (SupportTicketConfirmation $mailable) use ($ticket) {
            return $mailable->hasTo($ticket->requester_email);
        });
    }

    public function test_create_returns_successful_result(): void
    {
        Mail::fake();

        $ticket = SupportTicket::factory()->pending()->create();
        $gateway = new MailGateway();

        $result = $gateway->create($ticket);

        $this->assertSame(TicketSystem::Mail, $result->ticketSystemType);
        $this->assertFalse($result->creationError);
        $this->assertNull($result->errorMessage);
        $this->assertNotNull($result->ticketNumber);
    }

    public function test_create_generates_reference_with_configured_prefix(): void
    {
        Mail::fake();

        $ticket = SupportTicket::factory()->pending()->create();
        $gateway = new MailGateway();

        $result = $gateway->create($ticket);

        $this->assertSame(sprintf('TEST-%d', $ticket->id), $result->ticketNumber);
    }

    public function test_create_generates_reference_with_custom_prefix(): void
    {
        Mail::fake();
        config(['support.mail.reference_prefix' => 'REQ']);

        $ticket = SupportTicket::factory()->pending()->create();
        $gateway = new MailGateway();

        $result = $gateway->create($ticket);

        $this->assertSame(sprintf('REQ-%d', $ticket->id), $result->ticketNumber);
    }

    public function test_create_returns_error_result_on_mail_failure(): void
    {
        Mail::shouldReceive('to')
            ->andThrow(new \Exception('SMTP connection refused'));

        $ticket = SupportTicket::factory()->pending()->create();
        $gateway = new MailGateway();

        $result = $gateway->create($ticket);

        $this->assertSame(TicketSystem::Mail, $result->ticketSystemType);
        $this->assertTrue($result->creationError);
        $this->assertNull($result->ticketNumber);
        $this->assertSame('SMTP connection refused', $result->errorMessage);
    }

    public function test_fallback_mode_queues_both_emails(): void
    {
        Mail::fake();

        $ticket = SupportTicket::factory()->pending()->create();
        $gateway = new MailGateway(isFallbackStrategy: true);

        $gateway->create($ticket);

        Mail::assertQueued(SupportTicketMessage::class);
        Mail::assertQueued(SupportTicketConfirmation::class);
    }

    public function test_non_fallback_mode_queues_both_emails(): void
    {
        Mail::fake();

        $ticket = SupportTicket::factory()->pending()->create();
        $gateway = new MailGateway(isFallbackStrategy: false);

        $gateway->create($ticket);

        Mail::assertQueued(SupportTicketMessage::class, 1);
        Mail::assertQueued(SupportTicketConfirmation::class, 1);
    }
}
