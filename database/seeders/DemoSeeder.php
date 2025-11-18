<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Sample\DemoUserSeeder;
use Illuminate\Database\Seeder;

/**
 * This is a seeder intended for a sample data set used for development, testing, and demos.
 *
 * You CANNOT make assumptions about any of this data existing during development. This is only our
 * sample data set, and the production values may differ!
 *
 * For E2E testing, you should list only the seeders that are necessary to run the tests in the
 * `cypress/support/seeders.ts` file.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(StakeholderSeeder::class);
        $this->call(DemoUserSeeder::class);

        // Add additional seeders here as needed
    }
}
