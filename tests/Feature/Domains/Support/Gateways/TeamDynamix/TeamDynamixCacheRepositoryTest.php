<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Support\Gateways\TeamDynamix;

use App\Domains\Support\Exceptions\TdxLookupFailedException;
use App\Domains\Support\Gateways\TeamDynamix\TeamDynamixCacheRepository;
use DateTime;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Northwestern\Sysdev\TeamDynamix\Api\Client\SelfService\ServiceCatalog;
use Northwestern\Sysdev\TeamDynamix\Api\Client\Ticket\Ticket;
use Northwestern\Sysdev\TeamDynamix\Api\Client\Ticket\TicketPriority;
use Northwestern\Sysdev\TeamDynamix\Api\Client\Ticket\TicketStatus;
use Northwestern\Sysdev\TeamDynamix\Api\Client\Ticket\TicketType;
use Northwestern\Sysdev\TeamDynamix\Api\Entity\RateLimit;
use Northwestern\Sysdev\TeamDynamix\Api\Entity\Response;
use Northwestern\Sysdev\TeamDynamix\Laravel\TeamDynamixService;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(TeamDynamixCacheRepository::class)]
final class TeamDynamixCacheRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_it_resolves_all_supported_lookup_types(): void
    {
        $tdx = Mockery::mock(TeamDynamixService::class);

        $ticketType = Mockery::mock(TicketType::class);
        $ticketType->shouldReceive('all')->once()->andReturn(
            $this->response([
                ['ID' => 11, 'Name' => 'Default'],
            ])
        );

        $ticket = Mockery::mock(Ticket::class);
        $ticket->shouldReceive('allForms')->once()->andReturn(
            $this->response([
                ['ID' => 21, 'Name' => 'NU Base Service Request'],
            ])
        );

        $ticketStatus = Mockery::mock(TicketStatus::class);
        $ticketStatus->shouldReceive('all')->once()->andReturn(
            $this->response([
                ['ID' => 31, 'Name' => 'New'],
            ])
        );

        $ticketPriority = Mockery::mock(TicketPriority::class);
        $ticketPriority->shouldReceive('all')->once()->andReturn(
            $this->response([
                ['ID' => 41, 'Name' => 'Low (P4)'],
            ])
        );

        $serviceCatalog = Mockery::mock(ServiceCatalog::class);
        $serviceCatalog->shouldReceive('all')->once()->andReturn(
            $this->response([
                ['ID' => 51, 'Name' => 'Northwestern Laravel Starter'],
            ])
        );

        $tdx->shouldReceive('ticketType')->andReturn($ticketType);
        $tdx->shouldReceive('ticket')->andReturn($ticket);
        $tdx->shouldReceive('ticketStatus')->andReturn($ticketStatus);
        $tdx->shouldReceive('ticketPriority')->andReturn($ticketPriority);
        $tdx->shouldReceive('serviceCatalog')->andReturn($serviceCatalog);

        $repo = new TeamDynamixCacheRepository($tdx);

        $this->assertSame(11, $repo->findTicketTypeId('Default'));
        $this->assertSame(21, $repo->findTicketFormTypeId('NU Base Service Request'));
        $this->assertSame(31, $repo->findTicketStatusId('New'));
        $this->assertSame(41, $repo->findTicketPriorityId('Low (P4)'));
        $this->assertSame(51, $repo->findServiceId('Northwestern Laravel Starter'));
    }

    public function test_it_uses_cache_for_repeated_lookups(): void
    {
        $tdx = Mockery::mock(TeamDynamixService::class);
        $ticketType = Mockery::mock(TicketType::class);

        $ticketType->shouldReceive('all')->once()->andReturn(
            $this->response([
                ['ID' => 99, 'Name' => 'Default'],
            ])
        );

        $tdx->shouldReceive('ticketType')->andReturn($ticketType);

        $repo = new TeamDynamixCacheRepository($tdx);

        $this->assertSame(99, $repo->findTicketTypeId('Default'));
        $this->assertSame(99, $repo->findTicketTypeId('Default'));
    }

    public function test_it_throws_when_lookup_value_is_missing(): void
    {
        $tdx = Mockery::mock(TeamDynamixService::class);
        $ticketType = Mockery::mock(TicketType::class);

        $ticketType->shouldReceive('all')->once()->andReturn(
            $this->response([
                ['ID' => 1, 'Name' => 'Not Default'],
            ])
        );

        $tdx->shouldReceive('ticketType')->andReturn($ticketType);

        $repo = new TeamDynamixCacheRepository($tdx);

        $this->expectException(TdxLookupFailedException::class);
        $this->expectExceptionMessageIsOrContains("Unable to find Ticket Type with value 'Default' in TeamDynamix.");

        $repo->findTicketTypeId('Default');
    }

    /**
     * @param  array<int, array<string, mixed>>  $body
     */
    private function response(array $body): Response
    {
        return new Response(
            url: 'https://example.test/tdx',
            rateLimit: new RateLimit(remaining: 99, limit: 100, resetAt: new DateTime('+1 minute')),
            body: json_encode($body),
        );
    }
}
