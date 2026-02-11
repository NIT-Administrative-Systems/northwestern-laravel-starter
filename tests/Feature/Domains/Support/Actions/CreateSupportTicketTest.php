<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Support\Actions;

use App\Domains\Support\Actions\CreateSupportTicket;
use App\Domains\Support\Contracts\TicketSystemGateway;
use App\Domains\Support\Enums\TicketSystemEnum;
use App\Domains\Support\Gateway\CreationResult;
use App\Domains\Support\Gateway\TicketSystemGatewayFactory;
use App\Domains\Support\Gateways\Mail\MailGateway;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Repositories\SupportTicketRepository;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CreateSupportTicket::class)]
class CreateSupportTicketTest extends TestCase
{
    public function test_successful_primary_submission_does_not_trigger_fallback(): void
    {
        $ticket = SupportTicket::factory()->pending()->create();

        $gateway = Mockery::mock(TicketSystemGateway::class);
        $gateway->expects('create')
            ->andReturns(new CreationResult(
                ticketSystemType: TicketSystemEnum::MAIL,
                creationError: false,
                ticketNumber: 'SUP-1',
                errorMessage: null,
            ));

        $factory = Mockery::mock(TicketSystemGatewayFactory::class);
        $factory->allows('fallback')->never();

        $action = new CreateSupportTicket($gateway, $factory, resolve(SupportTicketRepository::class));
        $result = $action($ticket);

        $this->assertFalse($result->post_error);
        $this->assertSame('SUP-1', $result->ticket_number);
        $this->assertNull($result->fallback_sent_at);
    }

    public function test_failed_mail_primary_does_not_trigger_fallback(): void
    {
        $ticket = SupportTicket::factory()->pending()->create();

        $gateway = Mockery::mock(TicketSystemGateway::class);
        $gateway->expects('create')
            ->andReturns(new CreationResult(
                ticketSystemType: TicketSystemEnum::MAIL,
                creationError: true,
                ticketNumber: null,
                errorMessage: 'Mail server down',
            ));

        $factory = Mockery::mock(TicketSystemGatewayFactory::class);
        $factory->allows('fallback')->never();

        $action = new CreateSupportTicket($gateway, $factory, resolve(SupportTicketRepository::class));
        $result = $action($ticket);

        $this->assertTrue($result->post_error);
        $this->assertNull($result->fallback_sent_at);
    }

    public function test_failed_non_mail_primary_triggers_fallback(): void
    {
        $ticket = SupportTicket::factory()->pending()->create();

        $gateway = Mockery::mock(TicketSystemGateway::class);
        $gateway->expects('create')
            ->andReturns(new CreationResult(
                ticketSystemType: TicketSystemEnum::TEAM_DYNAMIX,
                creationError: true,
                ticketNumber: null,
                errorMessage: 'TDX timeout',
            ));

        $fallbackGateway = Mockery::mock(MailGateway::class);
        $fallbackGateway->expects('create')
            ->andReturns(new CreationResult(
                ticketSystemType: TicketSystemEnum::MAIL,
                creationError: false,
                ticketNumber: 'SUP-1',
                errorMessage: null,
            ));

        $factory = Mockery::mock(TicketSystemGatewayFactory::class);
        $factory->expects('fallback')
            ->andReturns($fallbackGateway);

        $action = new CreateSupportTicket($gateway, $factory, resolve(SupportTicketRepository::class));
        $result = $action($ticket);

        $this->assertTrue($result->post_error);
        $this->assertNotNull($result->fallback_sent_at);
    }

    public function test_fallback_sent_at_not_set_when_fallback_also_fails(): void
    {
        $ticket = SupportTicket::factory()->pending()->create();

        $gateway = Mockery::mock(TicketSystemGateway::class);
        $gateway->expects('create')
            ->andReturns(new CreationResult(
                ticketSystemType: TicketSystemEnum::TEAM_DYNAMIX,
                creationError: true,
                ticketNumber: null,
                errorMessage: 'TDX down',
            ));

        $fallbackGateway = Mockery::mock(MailGateway::class);
        $fallbackGateway->expects('create')
            ->andReturns(new CreationResult(
                ticketSystemType: TicketSystemEnum::MAIL,
                creationError: true,
                ticketNumber: null,
                errorMessage: 'Mail also failed',
            ));

        $factory = Mockery::mock(TicketSystemGatewayFactory::class);
        $factory->expects('fallback')
            ->andReturns($fallbackGateway);

        $action = new CreateSupportTicket($gateway, $factory, resolve(SupportTicketRepository::class));
        $result = $action($ticket);

        $this->assertTrue($result->post_error);
        $this->assertNull($result->fallback_sent_at);
    }

    public function test_primary_result_is_recorded_on_ticket(): void
    {
        $ticket = SupportTicket::factory()->pending()->create();

        $gateway = Mockery::mock(TicketSystemGateway::class);
        $gateway->expects('create')
            ->andReturns(new CreationResult(
                ticketSystemType: TicketSystemEnum::TEAM_DYNAMIX,
                creationError: false,
                ticketNumber: '1234567',
                errorMessage: null,
            ));

        $factory = Mockery::mock(TicketSystemGatewayFactory::class);

        $action = new CreateSupportTicket($gateway, $factory, resolve(SupportTicketRepository::class));
        $result = $action($ticket);

        $this->assertSame(TicketSystemEnum::TEAM_DYNAMIX, $result->ticketing_system);
        $this->assertSame('1234567', $result->ticket_number);
        $this->assertFalse($result->post_error);
        $this->assertNotNull($result->posted_to_ticketing_system_at);
    }
}
