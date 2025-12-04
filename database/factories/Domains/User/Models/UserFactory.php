<?php

declare(strict_types=1);

namespace Database\Factories\Domains\User\Models;

use App\Domains\User\Enums\AffiliationEnum;
use App\Domains\User\Enums\AuthTypeEnum;
use App\Domains\User\Enums\SystemRoleEnum;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<model-property<User>, mixed>
     */
    public function definition(): array
    {
        return [
            'auth_type' => AuthTypeEnum::SSO,
            'username' => $this->generateUniqueUsername(),
            'primary_affiliation' => fake()->randomElement(AffiliationEnum::cases()),
            'employee_id' => fake()->numerify('#######'),
            'hr_employee_id' => fake()->numerify('#######'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'job_titles' => [fake()->jobTitle()],
            'departments' => [fake()->company()],
            'timezone' => fake()->timezone(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if ($user->auth_type === AuthTypeEnum::SSO) {
                $user->assignRole(SystemRoleEnum::NORTHWESTERN_USER);
            }
        });
    }

    /**
     * Faker's `unique()` method is only unique in the context of the current instance. This means that the uniqueness
     * is not guaranteed across different instances of the Faker generator and could result in a unique constraint
     * violation at the database level.
     */
    private function generateUniqueUsername(): string
    {
        do {
            $username = fake()->unique()->userName();
        } while (User::whereUsername($username)->exists());

        return $username;
    }

    public function affiliate(): self
    {
        return $this->state(fn () => [
            'auth_type' => AuthTypeEnum::LOCAL,
            'primary_affiliation' => AffiliationEnum::AFFILIATE,
            'employee_id' => null,
            'hr_employee_id' => null,
            'job_titles' => [],
            'departments' => [],
            'last_directory_sync_at' => null,
        ]);
    }

    public function faculty(): self
    {
        return $this->state(fn () => [
            'primary_affiliation' => AffiliationEnum::FACULTY,
        ]);
    }

    public function staff(): self
    {
        return $this->state(fn () => [
            'primary_affiliation' => AffiliationEnum::STAFF,
        ]);
    }

    public function api(): self
    {
        return $this->state(function () {
            return [
                'auth_type' => AuthTypeEnum::API,
                'primary_affiliation' => AffiliationEnum::OTHER,
                'username' => 'api-' . fake()->unique()->userName(),
                'email' => null,
                'first_name' => fake()->company(),
                'last_name' => 'API',
                'employee_id' => null,
                'phone' => null,
                'job_titles' => [],
                'departments' => [],
                'last_directory_sync_at' => null,
            ];
        });
    }

    public function student(): self
    {
        return $this->state(fn () => [
            'email' => sprintf('%s.%s@u.northwestern.edu', fake()->userName(), fake()->year()),
            'primary_affiliation' => AffiliationEnum::STUDENT,
            'job_titles' => [],
            'departments' => [],
        ]);
    }
}
