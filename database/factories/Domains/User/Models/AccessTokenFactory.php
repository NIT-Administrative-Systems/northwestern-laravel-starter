<?php

declare(strict_types=1);

namespace Database\Factories\Domains\User\Models;

use App\Domains\User\Models\AccessToken;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AccessToken>
 */
class AccessTokenFactory extends Factory
{
    protected $model = AccessToken::class;

    /**
     * @return array<model-property<AccessToken>, mixed>
     */
    public function definition(): array
    {
        $rawToken = Str::random(length: 64);

        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, asText: true),
            'expires_at' => now()->addDays(90),
            'allowed_ips' => null,
            'usage_count' => 0,
            'last_used_at' => null,
            'expiration_notified_at' => null,
            'revoked_at' => null,
            'rotated_from_token_id' => null,
            'rotated_by_user_id' => null,
            'token_hash' => AccessToken::hashFromPlain($rawToken),
            'token_prefix' => mb_substr($rawToken, 0, 5),
        ];
    }
}
