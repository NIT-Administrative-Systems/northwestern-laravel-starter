<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Seed the database once per process alongside migrate:fresh.
     */
    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
