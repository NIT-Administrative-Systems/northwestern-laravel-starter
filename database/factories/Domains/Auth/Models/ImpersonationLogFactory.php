<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Auth\Models;

use App\Domains\Auth\Models\ImpersonationLog;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImpersonationLog>
 */
class ImpersonationLogFactory extends Factory
{
    protected $model = ImpersonationLog::class;

    /** @return array<model-property<ImpersonationLog>, mixed> */
    public function definition(): array
    {
        return [
            'impersonator_user_id' => User::factory(),
            'impersonated_user_id' => User::factory(),
        ];
    }
}
