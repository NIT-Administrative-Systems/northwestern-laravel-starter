<?php

declare(strict_types=1);

namespace Database\Factories\Domains\User\Models;

use App\Domains\Core\Enums\ApiRequestFailureEnum;
use App\Domains\User\Models\ApiRequestLog;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

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
        $failureReason = $this->faker->boolean(70)
            ? null
            : Arr::random(ApiRequestFailureEnum::cases());

        return [
            'trace_id' => $this->faker->uuid(),
            'user_id' => User::factory()->api(),
            'access_token_id' => null,
            'method' => $this->faker->randomElement(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
            'path' => $this->faker->randomElement([
                '/api/v1/data',
                '/api/v1/users/123/profile',
                '/api/v2/reports/summary',
            ]),
            'route_name' => $this->faker->slug(nbWords: random_int(0, 3)),
            'response_bytes' => $this->faker->boolean(80) ? $this->faker->numberBetween(100, 5000) : null,
            'ip_address' => $this->faker->ipv4(),
            'status_code' => $failureReason ? 401 : $this->faker->randomElement([200, 201, 204]),
            'duration_ms' => $this->faker->numberBetween(10, 800),
            'user_agent' => $this->faker->userAgent(),
            'failure_reason' => $failureReason,
            'created_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    public function successful(int $accessTokenId): self
    {
        return $this->state(fn (array $attributes) => [
            'access_token_id' => $accessTokenId,
            'status_code' => $this->faker->randomElement([200, 201, 204]),
            'failure_reason' => null,
        ]);
    }

    public function failed(?ApiRequestFailureEnum $reason = null): self
    {
        return $this->state(fn (array $attributes) => [
            'access_token_id' => null,
            'status_code' => 401,
            'failure_reason' => $reason ?? Arr::random(ApiRequestFailureEnum::cases()),
        ]);
    }
}
