<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Core\Services\ConfigValidation;

use App\Domains\Core\Services\ConfigValidation\QueueValidator;
use Illuminate\Support\Facades\Redis;
use Mockery;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * QueueValidator is outside the coverage gate,
 * since it needs a real queue connection.
 */
#[CoversNothing]
final class QueueValidatorTest extends TestCase
{
    /**
     * @return \Iterator<string, array{mixed}>
     */
    public static function pongs(): \Iterator
    {
        yield 'phpredis' => [true];
        yield 'predis status' => [new class
        {
            public function __toString(): string
            {
                return 'PONG';
            }
        }];
        yield 'older phpredis' => ['+PONG'];
    }

    #[DataProvider('pongs')]
    public function test_a_redis_queue_passes_when_redis_answers_the_ping(mixed $pong): void
    {
        config(['queue.default' => 'redis']);
        $this->redisAnswers(fn () => $pong);

        $this->assertTrue(new QueueValidator()->validate());
    }

    public function test_a_redis_queue_fails_when_redis_is_unreachable(): void
    {
        config(['queue.default' => 'redis']);
        $this->redisAnswers(fn () => throw new RuntimeException('Connection refused'));

        $this->assertFalse(new QueueValidator()->validate());
    }

    private function redisAnswers(\Closure $ping): void
    {
        $client = Mockery::mock();
        $client->shouldReceive('ping')->andReturnUsing($ping);

        $connection = Mockery::mock();
        $connection->shouldReceive('client')->andReturn($client);

        Redis::shouldReceive('connection')->andReturn($connection);
    }
}
