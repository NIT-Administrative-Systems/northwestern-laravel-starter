<?php

declare(strict_types=1);

namespace Database\Factories\Domains\User\Models;

use App\Domains\User\Models\ApiToken;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiToken>
 */
class ApiTokenFactory extends Factory
{
    protected $model = ApiToken::class;

    /**
     * @return array<model-property<ApiToken>, mixed>
     */
    public function definition(): array
    {
        $rawToken = Str::random(length: 64);

        return [
            'user_id' => User::factory(),
            'token_prefix' => mb_substr($rawToken, 0, 5),
            'token_hash' => ApiToken::hashFromPlain($rawToken),
            'valid_from' => now(),
            'valid_to' => null,
            'last_used_at' => null,
            'usage_count' => 0,
        ];
    }
}
