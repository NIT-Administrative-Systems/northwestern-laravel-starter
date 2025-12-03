<?php

declare(strict_types=1);

namespace App\Domains\User\Seeders;

use App\Domains\Core\Attributes\AutoSeed;
use App\Domains\Core\Contracts\IdempotentSeederInterface;
use App\Domains\User\Enums\PermissionEnum;
use App\Domains\User\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;

#[AutoSeed]
class PermissionSeeder extends Seeder implements IdempotentSeederInterface
{
    public function run(): void
    {
        collect(PermissionEnum::cases())->map(function (PermissionEnum $permission): array {
            return [
                'name' => $permission->value,
                'label' => $permission->getLabel(),
                'description' => $permission->description(),
                'system_managed' => $permission->isSystemManaged(),
                'api_relevant' => $permission->isApiRelevant(),
                'scope' => $permission->scope(),
            ];
        })->each(function (array $permissionData) {
            try {
                Permission::create($permissionData);
            } catch (PermissionAlreadyExists) {
                $existingPermission = Permission::findByName($permissionData['name']);

                $existingPermission->update(
                    Arr::except($permissionData, ['name'])
                );
            }
        });
    }
}
