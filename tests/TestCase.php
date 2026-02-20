<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Refresh the test database and seed once before any transactions begin.
     *
     * Overrides the default RefreshDatabase behavior to include seeding as
     * part of the one-time migration step. This means the seeder runs once
     * per process instead of once per test, dramatically reducing test
     * suite execution time.
     */
    protected function refreshTestDatabase()
    {
        if (! RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh', $this->migrateFreshUsing());

            $this->app[Kernel::class]->setArtisan(null);

            $this->artisan('db:seed');

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }
}
