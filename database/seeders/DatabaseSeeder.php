<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Core\Attributes\AutoSeed;
use App\Domains\Core\Services\IdempotentSeederResolver;
use Illuminate\Database\Seeder;

/**
 * Production-safe database seeder that runs across all environments.
 *
 * Automatically discovers and executes all seeders decorated with the #[AutoSeed] attribute
 * in dependency-resolved order. All seeders MUST be idempotent.
 *
 * Typical use cases:
 * - Seeding reference data (roles, permissions, statuses)
 * - Maintaining system-required records
 * - Updating existing records when data structure changes
 *
 * Avoid:
 * - Creating test/demo data (use DemoSeeder instead)
 * - Non-idempotent operations that fail on subsequent runs
 *
 * @see AutoSeed
 * @see IdempotentSeederResolver
 */
class DatabaseSeeder extends Seeder
{
    public function run(IdempotentSeederResolver $seederDiscovery): void
    {
        $seeders = $seederDiscovery->discover();

        foreach ($seeders as $seederInfo) {
            $this->call($seederInfo->className);
        }
    }
}
