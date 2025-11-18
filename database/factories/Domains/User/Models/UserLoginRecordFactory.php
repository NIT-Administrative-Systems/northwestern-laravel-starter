<?php

declare(strict_types=1);

namespace Database\Factories\Domains\User\Models;

use App\Domains\User\Enums\UserSegmentEnum;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserLoginRecord>
 */
class UserLoginRecordFactory extends Factory
{
    protected $model = UserLoginRecord::class;

    /** @return array<model-property<UserLoginRecord>, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'logged_in_at' => now(),
            'segment' => fake()->randomElement(UserSegmentEnum::cases()),
        ];
    }

    public function useExistingLookups(): self
    {
        return $this->state(function () {
            return [
                'user_id' => User::inRandomOrder()->value('id'),
            ];
        });
    }
}
