<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Core\Models\Concerns;

use App\Domains\Core\Enums\AuditEvent;
use App\Domains\Core\Models\Audit;
use App\Domains\Core\Models\Concerns\RecordsAuditEvents;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;

#[CoversTrait(RecordsAuditEvents::class)]
final class RecordsAuditEventsTest extends TestCase
{
    public function test_it_records_the_event_with_the_values_before_and_after(): void
    {
        $user = User::factory()->createOne();

        $user->recordAuditEvent(AuditEvent::ApplicationDisconnected, new: ['connected' => false], old: ['connected' => true]);

        $audit = $this->latestAudit($user);

        $this->assertSame('application_disconnected', $audit->event);
        $this->assertSame(['connected' => false], $audit->new_values);
        $this->assertSame(['connected' => true], $audit->old_values);
        $this->assertNull($audit->tags);
    }

    public function test_it_stores_tags_and_context_as_tags(): void
    {
        $user = User::factory()->createOne();

        $user->recordAuditEvent(AuditEvent::RoleAssigned, new: ['role' => 'Editor'], tags: ['system'], context: ['reason' => 'promoted']);

        $this->assertSame('system,reason: promoted', $this->latestAudit($user)->tags);
    }

    // The event's values and tags belong to it alone, not to the model's next audit.
    public function test_the_next_save_is_audited_as_itself(): void
    {
        config(['audit.console' => true]);
        $user = User::factory()->createOne();

        $user->recordAuditEvent(AuditEvent::RoleAssigned, new: ['role' => 'Editor'], tags: ['system']);
        $user->update(['first_name' => 'Changed']);

        $audit = $this->latestAudit($user);

        $this->assertSame('updated', $audit->event);
        $this->assertSame(['first_name' => 'Changed'], $audit->new_values);
        $this->assertNull($audit->tags);
    }

    private function latestAudit(User $user): Audit
    {
        $audit = Audit::query()
            ->where('auditable_type', $user->getMorphClass())
            ->where('auditable_id', $user->getKey())
            ->orderByDesc('id')
            ->first();

        $this->assertInstanceOf(Audit::class, $audit);

        return $audit;
    }
}
