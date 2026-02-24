<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Support\Gateways\TeamDynamix;

use App\Domains\Support\Enums\TicketSystemEnum;
use App\Domains\Support\Gateways\TeamDynamix\TeamDynamixCacheRepository;
use App\Domains\Support\Gateways\TeamDynamix\TeamDynamixGateway;
use App\Domains\Support\Models\SupportTicket;
use DateTime;
use Exception;
use Mockery;
use Northwestern\Sysdev\TeamDynamix\Api\Client\Ticket\Ticket as TeamDynamixTicketClient;
use Northwestern\Sysdev\TeamDynamix\Api\Entity\RateLimit;
use Northwestern\Sysdev\TeamDynamix\Api\Entity\Response;
use Northwestern\Sysdev\TeamDynamix\Api\Entity\Ticket\CreateTicket;
use Northwestern\Sysdev\TeamDynamix\Api\Entity\Ticket\TicketResponse;
use Northwestern\Sysdev\TeamDynamix\Laravel\TeamDynamixService;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(TeamDynamixGateway::class)]
class TeamDynamixGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'support.team-dynamix.ticket_type' => 'Default',
            'support.team-dynamix.form_type' => 'NU Base Service Request',
            'support.team-dynamix.ticket_status' => 'New',
            'support.team-dynamix.ticket_priority' => 'Low (P4)',
            'support.team-dynamix.service' => 'Northwestern Laravel Starter',
            'support.team-dynamix.assign_to_group' => 123,
        ]);
    }

    public function test_create_returns_successful_result_when_ticket_is_created(): void
    {
        $cache = $this->mockCacheRepository();
        $ticketClient = Mockery::mock(TeamDynamixTicketClient::class);
        $tdx = Mockery::mock(TeamDynamixService::class);

        $ticketClient->shouldReceive('create')
            ->once()
            ->withArgs(function (CreateTicket $ticketInfo, bool $notifyReviewer, bool $notifyRequestor, bool $notifyResponsible, bool $allowRequestorCreation): bool {
                $payload = $ticketInfo->toArray();

                $this->assertSame('Example issue', $payload['Title']);
                $this->assertStringContainsString('Need assistance', (string) $payload['Description']);
                $this->assertSame('requester@example.com', $payload['RequestorEmail']);
                $this->assertSame(123, $payload['ResponsibleGroupID']);
                $this->assertFalse($notifyReviewer);
                $this->assertTrue($notifyRequestor);
                $this->assertTrue($notifyResponsible);
                $this->assertFalse($allowRequestorCreation);

                return true;
            })
            ->andReturn($this->ticketResponse(4567));

        $tdx->shouldReceive('ticket')->once()->andReturn($ticketClient);

        $gateway = new TeamDynamixGateway($tdx, $cache);
        $ticket = SupportTicket::factory()->pending()->create([
            'subject' => 'Example issue',
            'details' => 'Need assistance',
            'requester_email' => 'requester@example.com',
        ]);

        $result = $gateway->create($ticket);

        $this->assertSame(TicketSystemEnum::TEAM_DYNAMIX, $result->ticketSystemType);
        $this->assertFalse($result->creationError);
        $this->assertSame('4567', $result->ticketNumber);
        $this->assertNull($result->errorMessage);
    }

    public function test_create_retries_and_returns_error_when_api_continues_failing(): void
    {
        $cache = $this->mockCacheRepository();
        $ticketClient = Mockery::mock(TeamDynamixTicketClient::class);
        $tdx = Mockery::mock(TeamDynamixService::class);

        $ticketClient->shouldReceive('create')
            ->times(3)
            ->andThrow(new Exception('TDX timeout'));

        $tdx->shouldReceive('ticket')->times(3)->andReturn($ticketClient);

        $gateway = new TeamDynamixGateway($tdx, $cache);
        $ticket = SupportTicket::factory()->pending()->create([
            'subject' => 'Retry test',
            'details' => 'simulated',
        ]);

        $result = $gateway->create($ticket);

        $this->assertTrue($result->creationError);
        $this->assertNull($result->ticketNumber);
        $this->assertSame('TDX timeout', $result->errorMessage);
    }

    public function test_create_returns_error_when_assignee_group_config_is_invalid(): void
    {
        config(['support.team-dynamix.assign_to_group' => null]);

        $cache = $this->mockCacheRepository();
        $tdx = Mockery::mock(TeamDynamixService::class);
        $tdx->shouldReceive('ticket')->never();

        $gateway = new TeamDynamixGateway($tdx, $cache);
        $ticket = SupportTicket::factory()->pending()->create();

        $result = $gateway->create($ticket);

        $this->assertTrue($result->creationError);
        $this->assertNull($result->ticketNumber);
        $this->assertStringContainsString('TDX_ASSIGNEE_ID is required', (string) $result->errorMessage);
    }

    private function mockCacheRepository(): TeamDynamixCacheRepository
    {
        $cache = Mockery::mock(TeamDynamixCacheRepository::class);
        $cache->shouldReceive('findTicketTypeId')->andReturn(1);
        $cache->shouldReceive('findTicketFormTypeId')->andReturn(2);
        $cache->shouldReceive('findTicketStatusId')->andReturn(3);
        $cache->shouldReceive('findTicketPriorityId')->andReturn(4);
        $cache->shouldReceive('findServiceId')->andReturn(5);

        return $cache;
    }

    private function ticketResponse(int $id): TicketResponse
    {
        return TicketResponse::fromResponse(new Response(
            url: 'https://example.test/tdx/tickets',
            rateLimit: new RateLimit(remaining: 99, limit: 100, resetAt: new DateTime('+1 minute')),
            body: json_encode(['ID' => $id]),
        ));
    }
}
