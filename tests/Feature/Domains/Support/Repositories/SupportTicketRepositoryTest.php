<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Support\Repositories;

use App\Domains\Support\Enums\TicketSystem;
use App\Domains\Support\Gateways\CreationResult;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Repositories\SupportTicketRepository;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SupportTicketRepository::class)]
class SupportTicketRepositoryTest extends TestCase
{
    public function test_create_persists_ticket_with_user_association(): void
    {
        $user = User::factory()->create(['email' => 'submitter@northwestern.edu']);
        $ticket = new SupportTicket([
            'subject' => 'Test subject',
            'details' => 'Test details',
        ]);

        $result = $this->repo()->create($user, $ticket);

        $this->assertTrue($result->exists);
        $this->assertSame($user->id, $result->user_id);
        $this->assertSame('submitter@northwestern.edu', $result->requester_email);
        $this->assertSame('Test subject', $result->subject);
    }

    public function test_create_populates_requester_email_from_user(): void
    {
        $user = User::factory()->create(['email' => 'jane@northwestern.edu']);
        $ticket = new SupportTicket([
            'subject' => 'Email test',
            'details' => 'Details',
        ]);

        $result = $this->repo()->create($user, $ticket);

        $this->assertSame('jane@northwestern.edu', $result->requester_email);
    }

    public function test_update_post_status_records_successful_result(): void
    {
        $ticket = SupportTicket::factory()->pending()->create();

        $result = new CreationResult(
            ticketSystemType: TicketSystem::Mail,
            creationError: false,
            ticketNumber: 'SUP-1',
            errorMessage: null,
        );

        $updated = $this->repo()->updatePostStatus($ticket, $result);

        $this->assertSame(TicketSystem::Mail, $updated->ticketing_system);
        $this->assertSame('SUP-1', $updated->ticket_number);
        $this->assertFalse($updated->post_error);
        $this->assertNull($updated->error_message);
        $this->assertNotNull($updated->posted_to_ticketing_system_at);
    }

    public function test_update_post_status_records_failed_result(): void
    {
        $ticket = SupportTicket::factory()->pending()->create();

        $result = new CreationResult(
            ticketSystemType: TicketSystem::TeamDynamix,
            creationError: true,
            ticketNumber: null,
            errorMessage: 'TDX API timeout',
        );

        $updated = $this->repo()->updatePostStatus($ticket, $result);

        $this->assertSame(TicketSystem::TeamDynamix, $updated->ticketing_system);
        $this->assertNull($updated->ticket_number);
        $this->assertTrue($updated->post_error);
        $this->assertSame('TDX API timeout', $updated->error_message);
        $this->assertNull($updated->posted_to_ticketing_system_at);
    }

    public function test_update_post_status_does_not_set_timestamp_on_failure(): void
    {
        $ticket = SupportTicket::factory()->pending()->create();

        $result = new CreationResult(
            ticketSystemType: TicketSystem::Mail,
            creationError: true,
            ticketNumber: null,
            errorMessage: 'Mail send failed',
        );

        $updated = $this->repo()->updatePostStatus($ticket, $result);

        $this->assertNull($updated->posted_to_ticketing_system_at);
    }

    protected function repo(): SupportTicketRepository
    {
        return resolve(SupportTicketRepository::class);
    }
}
