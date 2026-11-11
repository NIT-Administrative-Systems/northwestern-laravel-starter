<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Factories\Domains\Access\Models;

use App\Domains\Access\Enums\RoleTypeEnum;
use App\Domains\Access\Models\Role;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class RoleFactoryTest extends TestCase
{
    public function test_roles_of_one_type_each_get_their_own_name(): void
    {
        $roles = Role::factory()->count(2)->forRoleType(RoleTypeEnum::ApplicationRole)->create();

        $this->assertCount(2, $roles->pluck('name')->unique());
        $this->assertTrue($roles->every(fn (Role $role): bool => str_ends_with($role->name, '-' . RoleTypeEnum::ApplicationRole->value)));
    }
}
