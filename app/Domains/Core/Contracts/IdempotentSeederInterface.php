<?php

declare(strict_types=1);

namespace App\Domains\Core\Contracts;

use App\Domains\Core\Seeders\IdempotentSeeder;
use Database\Seeders\DatabaseSeeder;

/**
 * You can implement this interface to mark a seeder as idempotent and allow {@see DatabaseSeeder} to run it.
 *
 * In most cases, you should just extend {@see IdempotentSeeder}, but in cases where you need to do something
 * more complex than a lookup by exactly one unique column, you might opt to implement this interface and do
 * your own logic.
 */
interface IdempotentSeederInterface
{
    public function run(): void;
}
