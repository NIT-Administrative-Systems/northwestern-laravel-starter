<?php

declare(strict_types=1);

namespace App\Domains\Auth\Seeders;

use App\Domains\Auth\Enums\RoleTypeEnum;
use App\Domains\Auth\Models\RoleType;
use App\Domains\Core\Attributes\AutoSeed;
use App\Domains\Core\Seeders\IdempotentSeeder;

#[AutoSeed]
class RoleTypeSeeder extends IdempotentSeeder
{
    protected string $slugColumn = 'slug';

    protected string $model = RoleType::class;

    public function data(): array
    {
        return collect(RoleTypeEnum::cases())->map(function (RoleTypeEnum $roleType): array {
            return [
                'slug' => $roleType->value,
                'label' => $roleType->getLabel(),
            ];
        })->toArray();
    }
}
