<?php

declare(strict_types=1);

namespace App\Domains\User\Models\Concerns;

use App\Domains\User\Enums\AuditEvent;
use Northwestern\SysDev\Chassis\Models\Concerns\RecordsCustomAudits;

/**
 * Records an {@see AuditEvent} on an auditable model, for a change its own model events don't
 * capture, such as a role assigned or a credential revoked on a person's behalf. The audit's
 * user is whoever is signed in.
 *
 * ```php
 * $user->recordAuditEvent(AuditEvent::ApplicationDisconnected, new: ['client_id' => $client->getKey()]);
 * $user->recordAuditEvent(AuditEvent::RoleAssigned, new: $after, old: $before, tags: [$origin->value], context: ['reason' => 'promoted']);
 * ```
 *
 * Tags are stored with the audit; each context entry is added as a "key: value" tag. An event
 * with no values before or after isn't recorded, as owen-it skips empty audits.
 *
 * @phpstan-require-implements \OwenIt\Auditing\Contracts\Auditable
 */
trait RecordsAuditEvents
{
    use RecordsCustomAudits;

    /**
     * @param  array<string, mixed>  $new
     * @param  array<string, mixed>  $old
     * @param  list<string>  $tags
     * @param  array<string, mixed>  $context
     */
    public function recordAuditEvent(AuditEvent $event, array $new, array $old = [], array $tags = [], array $context = []): void
    {
        $this->auditCustomTags = $tags;
        $this->auditCustomContext = $context;

        try {
            $this->recordCustomAudit($event->value, $new, $old);
        } finally {
            unset($this->auditCustomTags, $this->auditCustomContext);
        }
    }
}
