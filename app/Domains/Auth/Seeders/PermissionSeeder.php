<?php

declare(strict_types=1);

namespace App\Domains\Auth\Seeders;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\Permission;
use App\Domains\Core\Attributes\AutoSeed;
use App\Domains\Core\Contracts\IdempotentSeederInterface;
use App\Domains\Core\Seeders\Concerns\AuditsSeederChanges;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;

#[AutoSeed]
class PermissionSeeder extends Seeder implements IdempotentSeederInterface
{
    use AuditsSeederChanges;

    public function run(): void
    {
        $this->withAuditing([Permission::class], function () {
            collect(SystemPermission::cases())->map(function (SystemPermission $permission): array {
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
        });
    }
}
