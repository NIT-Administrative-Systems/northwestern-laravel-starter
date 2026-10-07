<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Support\Actions;

use App\Domains\Support\Actions\CreateSupportTicket;
use App\Domains\Support\Contracts\TicketSystemGateway;
use App\Domains\Support\Enums\TicketSystem;
use App\Domains\Support\Gateways\CreationResult;
use App\Domains\Support\Gateways\Mail\SupportTicketConfirmation;
use App\Domains\Support\Gateways\Mail\SupportTicketMessage;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\User\Models\User;
use App\Providers\SupportServiceProvider;
use Exception;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CreateSupportTicket::class)]
final class CreateSupportTicketTest extends TestCase
{
    private const array REQUEST = ['subject' => 'Help with login', 'details' => 'I cannot sign in.'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['support.mail.to' => 'support@northwestern.edu']);
    }

    public function test_it_saves_the_request_as_the_persons_ticket(): void
    {
        $this->useTicketSystem(new CreationResult(TicketSystem::TeamDynamix, creationError: false, ticketNumber: '1234567', errorMessage: null));
        $person = User::factory()->create(['email' => 'willie@northwestern.edu']);

        $ticket = $this->createTicket($person);

        $this->assertTrue($ticket->exists);
        $this->assertSame($person->id, $ticket->user_id);
        $this->assertSame('willie@northwestern.edu', $ticket->requester_email);
        $this->assertSame('Help with login', $ticket->subject);
    }

    public function test_it_records_what_the_ticket_system_returned(): void
    {
        Mail::fake();
        $this->useTicketSystem(new CreationResult(TicketSystem::TeamDynamix, creationError: false, ticketNumber: '1234567', errorMessage: null));

        $ticket = $this->createTicket()->fresh();

        $this->assertInstanceOf(SupportTicket::class, $ticket);
        $this->assertSame(TicketSystem::TeamDynamix, $ticket->ticketing_system);
        $this->assertSame('1234567', $ticket->ticket_number);
        $this->assertFalse($ticket->post_error);
        $this->assertNotNull($ticket->posted_to_ticketing_system_at);
        $this->assertTrue($ticket->wasPostedSuccessfully());
        $this->assertNull($ticket->fallback_sent_at);
        Mail::assertNothingQueued();
    }

    public function test_the_mail_ticket_system_emails_the_support_team_and_the_person(): void
    {
        Mail::fake();
        config(['support.enabled' => true, 'support.driver' => 'mail']);
        new SupportServiceProvider($this->app)->register();

        $ticket = $this->createTicket();

        $this->assertSame(TicketSystem::Mail, $ticket->ticketing_system);
        $this->assertSame("SUP-{$ticket->id}", $ticket->ticket_number);
        Mail::assertQueued(SupportTicketMessage::class, fn (SupportTicketMessage $mail): bool => $mail->hasTo('support@northwestern.edu'));
        Mail::assertQueued(SupportTicketConfirmation::class);
    }

    // The request reaches the support team even when the ticket system is down.
    public function test_a_failed_ticket_system_falls_back_to_email(): void
    {
        Mail::fake();
        $this->useTicketSystem(new CreationResult(TicketSystem::TeamDynamix, creationError: true, ticketNumber: null, errorMessage: 'TDX timeout'));

        $ticket = $this->createTicket();

        $this->assertTrue($ticket->post_error);
        $this->assertSame('TDX timeout', $ticket->error_message);
        $this->assertNull($ticket->posted_to_ticketing_system_at);
        $this->assertNotNull($ticket->fallback_sent_at);
        Mail::assertQueued(SupportTicketMessage::class, function (SupportTicketMessage $mail): bool {
            $mail->build();

            return $mail->buildViewData()['fallbackMode'] === true;
        });
    }

    public function test_the_fallback_is_not_marked_sent_when_email_fails_too(): void
    {
        $this->useTicketSystem(new CreationResult(TicketSystem::TeamDynamix, creationError: true, ticketNumber: null, errorMessage: 'TDX down'));
        Mail::shouldReceive('to')->andThrow(new Exception('SMTP connection refused'));

        $ticket = $this->createTicket();

        $this->assertTrue($ticket->post_error);
        $this->assertNull($ticket->fallback_sent_at);
    }

    // Email failing is already the email path; there's nothing left to fall back to.
    public function test_a_failed_mail_ticket_system_does_not_fall_back(): void
    {
        Mail::fake();
        $this->useTicketSystem(new CreationResult(TicketSystem::Mail, creationError: true, ticketNumber: null, errorMessage: 'Mail server down'));

        $ticket = $this->createTicket();

        $this->assertTrue($ticket->post_error);
        $this->assertNull($ticket->fallback_sent_at);
        Mail::assertNothingQueued();
    }

    private function createTicket(?User $person = null): SupportTicket
    {
        return resolve(CreateSupportTicket::class)($person ?? User::factory()->create(), self::REQUEST);
    }

    /**
     * Stands in for the configured ticket system, as an application's own gateway would.
     */
    private function useTicketSystem(CreationResult $result): void
    {
        $this->app->bind(TicketSystemGateway::class, fn (): TicketSystemGateway => new readonly class($result) implements TicketSystemGateway
        {
            public function __construct(private CreationResult $result)
            {
            }

            public function create(SupportTicket $ticket): CreationResult
            {
                return $this->result;
            }
        });
    }
}
