<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Sample\DemoUserSeeder;
use Illuminate\Database\Seeder;

/**
 * Sample data for local development, tests and demos.
 *
 * Don't rely on any of it existing: it's only a
 * sample, and production's data will differ.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // StakeholderSeeder isn't called here, as it looks people up in Directory Search.
        //
        // Local environments get an administrator from DemoUserSeeder instead.
        $this->call(DemoUserSeeder::class);

        // Add additional seeders here as needed
    }
}
