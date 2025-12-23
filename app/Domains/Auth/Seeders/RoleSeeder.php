<?php

declare(strict_types=1);

namespace App\Domains\Auth\Seeders;

use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\Auth\Enums\RoleTypeEnum;
use App\Domains\Auth\Enums\SystemRoleEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\Auth\Models\RoleType;
use App\Domains\Core\Attributes\AutoSeed;
use App\Domains\Core\Contracts\IdempotentSeederInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Spatie\Permission\PermissionRegistrar;

#[AutoSeed(
    dependsOn: [
        RoleTypeSeeder::class,
        PermissionSeeder::class,
    ],
)]
class RoleSeeder extends Seeder implements IdempotentSeederInterface
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $systemManagedRoleId = RoleType::where('slug', RoleTypeEnum::SYSTEM_MANAGED)->value('id');

        $roles = collect([
            [
                'name' => SystemRoleEnum::SUPER_ADMINISTRATOR,
                'role_type_id' => $systemManagedRoleId,
                'permissions' => PermissionEnum::cases(),
            ],
            [
                'name' => SystemRoleEnum::NORTHWESTERN_USER,
                'role_type_id' => $systemManagedRoleId,
                'permissions' => [
                    // Add permissions as needed
                ],
            ],
        ]);

        $roles->each(function (array $roleAttrs) {
            $role = Role::updateOrCreate(
                Arr::only($roleAttrs, ['name']),
                Arr::except($roleAttrs, ['name', 'permissions']),
            );

            $role->syncPermissions($roleAttrs['permissions']);
        });
    }
}
