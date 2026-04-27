<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Auth\Models;

use App\Domains\Auth\Models\ApiRequestLog;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;
use Northwestern\SysDev\Chassis\Enums\ApiRequestFailure;

/**
 * @extends Factory<ApiRequestLog>
 */
class ApiRequestLogFactory extends Factory
{
    protected $model = ApiRequestLog::class;

    /**
     * @return array<model-property<ApiRequestLog>, mixed>
     */
    public function definition(): array
    {
        $failureReason = fake()->boolean(70)
            ? null
            : Arr::random(ApiRequestFailure::cases());

        return [
            'trace_id' => fake()->uuid(),
            'user_id' => User::factory()->api(),
            'access_token_id' => null,
            'method' => fake()->randomElement(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
            'path' => fake()->randomElement([
                '/api/v1/data',
                '/api/v1/users/123/profile',
                '/api/v2/reports/summary',
            ]),
            'route_name' => fake()->slug(nbWords: random_int(0, 3)),
            'request_bytes' => fake()->boolean(60) ? fake()->numberBetween(0, 2000) : null,
            'response_bytes' => fake()->boolean(80) ? fake()->numberBetween(100, 5000) : null,
            'ip_address' => fake()->ipv4(),
            'status_code' => $failureReason ? 401 : fake()->randomElement([200, 201, 204]),
            'duration_ms' => fake()->numberBetween(10, 800),
            'user_agent' => fake()->userAgent(),
            'failure_reason' => $failureReason,
            'created_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }

    public function successful(int $accessTokenId): self
    {
        return $this->state(fn (array $attributes) => [
            'access_token_id' => $accessTokenId,
            'status_code' => fake()->randomElement([200, 201, 204]),
            'failure_reason' => null,
        ]);
    }

    public function failed(?ApiRequestFailure $reason = null): self
    {
        return $this->state(fn (array $attributes) => [
            'access_token_id' => null,
            'status_code' => 401,
            'failure_reason' => $reason ?? Arr::random(ApiRequestFailure::cases()),
        ]);
    }
}
