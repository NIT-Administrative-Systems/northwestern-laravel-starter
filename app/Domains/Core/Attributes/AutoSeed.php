<?php

declare(strict_types=1);

namespace App\Domains\Core\Attributes;

use App\Domains\Core\Contracts\IdempotentSeederInterface;
use App\Domains\Core\Seeders\IdempotentSeeder;
use Attribute;
use Illuminate\Database\Seeder;

/**
 * Marks a seeder for automatic discovery and dependency-aware execution.
 *
 * Seeders decorated with this attribute are automatically discovered and executed
 * in dependency order during deployment across all environments.
 *
 * Requirements:
 * - Must extend {@see IdempotentSeeder} or implement {@see IdempotentSeederInterface}
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class AutoSeed
{
    /**
     * @param  list<class-string<IdempotentSeederInterface>>  $dependsOn  Array of seeder classes that must run first
     */
    public function __construct(
        public array $dependsOn = [],
    ) {
        //
    }
}
