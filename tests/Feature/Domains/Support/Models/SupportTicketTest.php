<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Support\Models;

use App\Domains\Support\Models\SupportTicket;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class SupportTicketTest extends TestCase
{
    public function test_support_tickets_are_kept_by_default(): void
    {
        $ticket = SupportTicket::factory()->create(['created_at' => now()->subYears(5)]);

        $this->artisan('model:prune', ['--model' => [SupportTicket::class]])->assertSuccessful();

        $this->assertNotNull(SupportTicket::withTrashed()->find($ticket->getKey()));
    }

    public function test_a_retention_period_deletes_older_tickets_for_good(): void
    {
        $old = SupportTicket::factory()->create(['created_at' => now()->subDays(31)]);
        $recent = SupportTicket::factory()->create(['created_at' => now()->subDays(29)]);

        config(['platform.retention.support_tickets' => 30]);
        $this->artisan('model:prune', ['--model' => [SupportTicket::class]])->assertSuccessful();

        $this->assertNull(SupportTicket::withTrashed()->find($old->getKey()));
        $this->assertNotNull(SupportTicket::query()->find($recent->getKey()));
    }
}
