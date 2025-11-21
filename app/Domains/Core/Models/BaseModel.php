<?php

declare(strict_types=1);

namespace App\Domains\Core\Models;

use App\Domains\Core\Attributes\AutomaticallyOrdered;
use App\Domains\Core\Models\Concerns\Auditable as AuditableConcern;
use App\Domains\Core\Models\Scopes\AutomaticallyOrderedScope;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use ReflectionClass;

/**
 * Base model with automatic audit logging.
 *
 * All models extending this class will automatically log create, update, and delete
 * operations to the `audits` table for complete audit trail of all data changes.
 *
 * Audit logs capture:
 * - The user who made the change
 * - When the change occurred
 * - What changed (old and new values)
 * - The IP address and user agent
 * - Additional context (e.g., Livewire component name)
 *
 * To customize auditing behavior:
 * - Override `transformAudit()` to modify audit data before saving
 * - Add fields to `$auditExclude` to exclude them from audit logs
 *
 * @see \App\Domains\User\Models\Audit
 */
abstract class BaseModel extends Model implements Auditable
{
    use AuditableConcern;

    protected static function booted(): void
    {
        static::registerAttributeBasedScopes();
    }

    protected static function registerAttributeBasedScopes(): void
    {
        $reflection = new ReflectionClass(static::class);

        $attributes = $reflection->getAttributes(AutomaticallyOrdered::class);
        if (count($attributes) > 0) {
            /** @var AutomaticallyOrdered $attribute */
            $attribute = $attributes[0]->newInstance();
            static::addGlobalScope(
                new AutomaticallyOrderedScope(
                    primary: $attribute->primary,
                    primaryDirection: $attribute->primaryDirection,
                    secondary: $attribute->secondary,
                    secondaryDirection: $attribute->secondaryDirection
                )
            );
        }
    }
}
