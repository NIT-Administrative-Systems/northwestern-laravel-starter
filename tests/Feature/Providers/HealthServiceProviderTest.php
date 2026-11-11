<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Providers\HealthServiceProvider;
use PHPUnit\Framework\Attributes\CoversNothing;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Facades\Health;
use Tests\TestCase;

#[CoversNothing]
final class HealthServiceProviderTest extends TestCase
{
    // REDIS_CLIENT names the library that would connect, not a store that uses Redis.
    public function test_redis_is_checked_only_when_a_store_uses_it(): void
    {
        config([
            'database.redis.client' => 'predis',
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'session.driver' => 'array',
        ]);

        $this->assertFalse($this->redisCheck()->shouldRun());

        config(['queue.default' => 'redis']);

        $this->assertTrue($this->redisCheck()->shouldRun());
    }

    private function redisCheck(): Check
    {
        Health::clearChecks();
        new HealthServiceProvider($this->app)->boot();

        return Health::registeredChecks()->sole(fn (Check $check): bool => $check instanceof RedisCheck);
    }
}
