<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Support\Policies;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Policies\SupportTicketPolicy;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SupportTicketPolicy::class)]
final class SupportTicketPolicyTest extends TestCase
{
    public function test_reading_tickets_needs_view_support_tickets(): void
    {
        $ticket = SupportTicket::factory()->create();
        $reader = User::factory()->create();
        $reader->givePermissionTo(SystemPermission::ViewSupportTickets);
        $someone = User::factory()->create();

        $this->assertTrue($reader->can('viewAny', SupportTicket::class));
        $this->assertTrue($reader->can('view', $ticket));
        $this->assertFalse($someone->can('viewAny', SupportTicket::class));
        $this->assertFalse($someone->can('view', $ticket));
    }
}
