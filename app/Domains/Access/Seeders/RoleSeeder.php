<?php

declare(strict_types=1);

namespace App\Domains\Access\Seeders;

use App\Domains\Access\Enums\RoleTypeEnum;
use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Enums\SystemRole;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\RoleType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Northwestern\SysDev\Chassis\Attributes\AutoSeed;
use Northwestern\SysDev\Chassis\Contracts\IdempotentSeederInterface;
use Northwestern\SysDev\Chassis\Seeding\Concerns\AuditsSeederChanges;
use Spatie\Permission\PermissionRegistrar;

#[AutoSeed(
    dependsOn: [
        RoleTypeSeeder::class,
        PermissionSeeder::class,
    ],
)]
class RoleSeeder extends Seeder implements IdempotentSeederInterface
{
    use AuditsSeederChanges;

    public function run(): void
    {
        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->withAuditing([Role::class], function () {
            $systemManagedRoleId = RoleType::where('slug', RoleTypeEnum::SystemManaged)->value('id');

            $roles = collect([
                [
                    'name' => SystemRole::SuperAdministrator,
                    'role_type_id' => $systemManagedRoleId,
                    'permissions' => SystemPermission::cases(),
                ],
                [
                    'name' => SystemRole::NorthwesternUser,
                    'role_type_id' => $systemManagedRoleId,
                    'assignment_locked' => true,
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

                $role->syncPermissionsWithAudit($roleAttrs['permissions']);
            });
        });
    }
}
