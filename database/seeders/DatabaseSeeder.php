<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Northwestern\SysDev\Chassis\Attributes\AutoSeed;
use Northwestern\SysDev\Chassis\Seeding\IdempotentSeederResolver;

/**
 * Seeds every environment, production included, and must be safe to run again.
 *
 * It finds every seeder marked #[AutoSeed] and
 * runs them in the order their dependencies
 * need, so each one must be idempotent.
 *
 * Use it for reference data, such as roles and
 * permissions, and records the system needs.
 * Demo and test data belong in DemoSeeder.
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
