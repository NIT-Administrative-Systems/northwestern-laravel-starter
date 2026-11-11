<?php

declare(strict_types=1);

namespace App\Domains\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Northwestern\SysDev\Chassis\Attributes\AutomaticallyOrdered;
use Northwestern\SysDev\Chassis\Models\Concerns\Auditable as AuditableConcern;
use Northwestern\SysDev\Chassis\Models\Scopes\AutomaticallyOrderedScope;
use OwenIt\Auditing\Contracts\Auditable;
use ReflectionClass;

/**
 * The model the others extend, so that creating, updating and deleting a record
 * is audited: who did it, when, what changed, and from which IP and browser.
 *
 * Add a column to `$auditExclude` to leave it out of audits.
 *
 * Override `transformAudit()` to change what's recorded.
 *
 * @see Audit
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
