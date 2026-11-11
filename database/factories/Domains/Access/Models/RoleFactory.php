<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Access\Models;

use App\Domains\Access\Enums\RoleTypeEnum;
use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Models\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\RoleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * @var array<string, int>
     */
    protected array $roleTypes = [];

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(),
            'role_type_id' => $this->roleTypeId(),
        ];
    }

    /**
     * @param  SystemPermission[]  $permissions
     */
    public function hasPermissions(array $permissions): static
    {
        return $this->afterCreating(function (Role $role) use ($permissions) {
            foreach ($permissions as $permission) {
                $role->givePermissionTo(
                    Permission::query()->where('name', $permission)->firstOrFail()
                );
            }
        });
    }

    public function systemManaged(): static
    {
        return $this->forRoleType(RoleTypeEnum::SystemManaged);
    }

    public function assignmentLocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'assignment_locked' => true,
        ]);
    }

    public function forRoleType(RoleTypeEnum $roleType): static
    {
        return $this->state(fn () => [
            'name' => fake()->unique()->slug() . '-' . $roleType->value,
            'role_type_id' => $this->roleTypeId($roleType),
        ]);
    }

    protected function roleTypeId(?RoleTypeEnum $type = null): int
    {
        if (! $this->roleTypes) {
            $this->roleTypes = RoleType::query()->pluck('id', 'slug')->all();
        }

        if (! $type instanceof RoleTypeEnum) {
            // Any type a person's role can have: an API Integration role suits only API users.
            return fake()->randomElement(array_diff_key($this->roleTypes, [RoleTypeEnum::ApiIntegration->value => true]));
        }

        return $this->roleTypes[$type->value];
    }
}
