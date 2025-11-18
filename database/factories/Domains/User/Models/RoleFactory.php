<?php

declare(strict_types=1);

namespace Database\Factories\Domains\User\Models;

use App\Domains\User\Enums\PermissionEnum;
use App\Domains\User\Enums\RoleTypeEnum;
use App\Domains\User\Models\Permission;
use App\Domains\User\Models\Role;
use App\Domains\User\Models\RoleType;
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
        /** @phpstan-ignore-next-line  */
        return [
            'name' => fake()->unique()->slug(),
            'role_type_id' => $this->getRoleType(),
        ];
    }

    /**
     * @param  PermissionEnum[]  $permissions
     */
    public function hasPermissions(array $permissions): static
    {
        return $this->afterCreating(function (Role $role) use ($permissions) {
            foreach ($permissions as $permission) {
                $role->givePermissionTo(
                    Permission::whereName($permission)->firstOrFail()
                );
            }
        });
    }

    public function systemManaged(): static
    {
        return $this->forRoleType(RoleTypeEnum::SYSTEM_MANAGED);
    }

    public function forRoleType(RoleTypeEnum $roleType): static
    {
        $name = fake()->unique()->slug() . '-' . $roleType->value;

        return $this->state(function () use ($roleType, $name) {
            return [
                'name' => $name,
                'role_type_id' => $this->getRoleType($roleType),
            ];
        });
    }

    protected function getRoleType(?RoleTypeEnum $type = null): int
    {
        if (! $this->roleTypes) {
            /** @phpstan-ignore-next-line */
            $this->roleTypes = RoleType::all()->pluck('id', 'slug.value')->all();
        }

        if (! $type) {
            return fake()->randomElement($this->roleTypes);
        }

        return $this->roleTypes[$type->value];
    }
}
