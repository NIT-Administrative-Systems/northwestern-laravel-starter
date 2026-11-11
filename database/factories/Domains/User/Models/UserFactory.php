<?php

declare(strict_types=1);

namespace Database\Factories\Domains\User\Models;

use App\Domains\Access\Enums\RoleModificationOrigin;
use App\Domains\Access\Enums\SystemRole;
use App\Domains\Access\Models\Role;
use App\Domains\Auth\Enums\AuthType;
use App\Domains\User\Enums\Affiliation;
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
            'auth_type' => AuthType::SSO,
            'username' => $this->generateUniqueUsername(),
            'primary_affiliation' => fake()->randomElement(Affiliation::cases()),
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
            if ($user->auth_type === AuthType::SSO) {
                $role = Role::query()->where('name', SystemRole::NorthwesternUser->value)->firstOrFail();
                $user->assignRoleWithAudit($role, RoleModificationOrigin::System);
            }
        });
    }

    public function affiliate(): static
    {
        return $this->state(fn () => [
            'auth_type' => AuthType::Local,
            'primary_affiliation' => Affiliation::Affiliate,
            'employee_id' => null,
            'hr_employee_id' => null,
            'job_titles' => [],
            'departments' => [],
            'last_directory_sync_at' => null,
        ]);
    }

    public function faculty(): static
    {
        return $this->state(fn () => [
            'primary_affiliation' => Affiliation::Faculty,
        ]);
    }

    public function staff(): static
    {
        return $this->state(fn () => [
            'primary_affiliation' => Affiliation::Staff,
        ]);
    }

    public function api(): static
    {
        return $this->state(fn () => [
            'auth_type' => AuthType::API,
            'primary_affiliation' => Affiliation::Other,
            'username' => 'api-' . fake()->unique()->userName(),
            'email' => null,
            'first_name' => fake()->company(),
            'last_name' => 'API',
            'employee_id' => null,
            'phone' => null,
            'job_titles' => [],
            'departments' => [],
            'last_directory_sync_at' => null,
        ]);
    }

    public function student(): static
    {
        return $this->state(fn () => [
            'email' => sprintf('%s.%s@u.northwestern.edu', fake()->userName(), fake()->year()),
            'primary_affiliation' => Affiliation::Student,
            'job_titles' => [],
            'departments' => [],
        ]);
    }

    /**
     * Faker's unique() is unique only within one Faker instance, so
     * a username can still exist in the database; check for it.
     */
    private function generateUniqueUsername(): string
    {
        do {
            $username = fake()->unique()->userName();
        } while (User::query()->where('username', $username)->exists());

        return $username;
    }
}
