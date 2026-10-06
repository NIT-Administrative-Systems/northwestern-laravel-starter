<?php

declare(strict_types=1);

namespace App\Domains\Core\Models\Concerns;

use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Records a custom audit event on this model, for a change its own model events don't capture,
 * such as a credential revoked on a person's behalf. The audit's user is whoever is signed in.
 *
 * @mixin Auditable
 */
trait RecordsCustomAudits
{
    /**
     * @param  array<string, mixed>  $new
     * @param  array<string, mixed>  $old
     */
    public function recordCustomAudit(string $event, array $new, array $old = []): void
    {
        $this->auditEvent = $event;
        $this->isCustomEvent = true;
        $this->auditCustomOld = $old;
        $this->auditCustomNew = $new;

        Event::dispatch(new AuditCustom($this));
    }
}
