<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Northwestern\SysDev\SOA\EventHub\Topic;

/**
 * Configures mock implementations for external API integrations.
 *
 * When mocking is enabled, API services use local data instead of making
 * actual HTTP requests to enable offline development and faster testing.
 */
class IntegrationServiceProvider extends ServiceProvider
{
    /**
     * Bind first-party API services that the application directly consumes.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bind vendor package APIs after other service providers load.
     *
     * Must run in boot() to prevent vendor packages from overriding our bindings.
     */
    public function boot(): void
    {
        $this->bindEventHubApi();
    }

    /**
     * Mock EventHub API to avoid actual message publishing during development/testing.
     */
    private function bindEventHubApi(): void
    {
        if (! config('nusoa.eventHub.mock')) {
            return;
        }

        $this->app->instance(Topic::class, \Mockery::mock(Topic::class, static function (MockInterface $mock) {
            $mock->allows('writeMessage')->andReturn((string) Str::uuid());
            $mock->allows('writeJsonMessage')->andReturn((string) Str::uuid());
        }));
    }
}
