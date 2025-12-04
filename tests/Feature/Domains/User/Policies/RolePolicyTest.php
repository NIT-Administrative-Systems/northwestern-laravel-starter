<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Policies;

use App\Domains\User\Enums\PermissionEnum;
use App\Domains\User\Models\User;
use App\Domains\User\Policies\RolePolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RolePolicy::class)]
class RolePolicyTest extends TestCase
{
    public function test_view_any_denies_user_without_permission(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->policy()->viewAny($user));
    }

    public function test_view_any_allows_user_with_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(PermissionEnum::VIEW_ROLES);

        $this->assertTrue($this->policy()->viewAny($user));
    }

    public function test_create_denies_user_without_permission(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->policy()->create($user));
    }

    public function test_create_allows_user_with_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(PermissionEnum::EDIT_ROLES);

        $this->assertTrue($this->policy()->create($user));
    }

    public function test_update_denies_user_without_permission(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->policy()->update($user));
    }

    public function test_update_allows_user_with_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(PermissionEnum::EDIT_ROLES);

        $this->assertTrue($this->policy()->update($user));
    }

    protected function policy(): RolePolicy
    {
        return resolve(RolePolicy::class);
    }
}
