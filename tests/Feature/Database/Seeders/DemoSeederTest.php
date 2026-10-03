<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Seeders;

use App\Domains\User\Models\User;
use Database\Seeders\DemoSeeder;
use Northwestern\SysDev\SOA\DirectorySearch;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(DemoSeeder::class)]
final class DemoSeederTest extends TestCase
{
    // db:rebuild runs this, so it must work offline: no Directory Search lookups, even with SUPER_ADMIN_NETIDS set.
    public function test_it_seeds_the_demo_users_without_calling_directory_search(): void
    {
        config(['platform.stakeholders.super_admins' => 'abc123']);
        $this->mock(DirectorySearch::class, function ($mock): void {
            $mock->shouldNotReceive('lookup');
            $mock->shouldNotReceive('lookupByNetId');
        });

        $this->seed(DemoSeeder::class);

        $this->assertTrue(User::query()->where('username', 'nuit.admin')->exists());
        $this->assertTrue(User::query()->where('username', 'generic.user')->exists());
    }
}
