<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Northwestern\SysDev\SOA\EventHub\Topic;

/**
 * Answers EventHub's topic API locally when EVENT_HUB_MOCK_ENABLED
 * is on, so that development and tests never publish a message.
 */
class IntegrationServiceProvider extends ServiceProvider
{
    /**
     * Bind the mocks after every provider registers, so a package can't replace them.
     */
    public function boot(): void
    {
        $this->bindEventHubApi();
    }

    private function bindEventHubApi(): void
    {
        if (! config('nusoa.eventHub.mock')) {
            return;
        }

        $this->app->instance(Topic::class, \Mockery::mock(Topic::class, static function (MockInterface $mock) {
            $mock->shouldReceive('writeMessage')->andReturn((string) Str::uuid());
            $mock->shouldReceive('writeJsonMessage')->andReturn((string) Str::uuid());
        }));
    }
}
