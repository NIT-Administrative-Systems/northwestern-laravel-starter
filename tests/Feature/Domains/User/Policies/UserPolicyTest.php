<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Policies;

use App\Domains\User\Enums\PermissionEnum;
use App\Domains\User\Models\User;
use App\Domains\User\Policies\UserPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UserPolicy::class)]
class UserPolicyTest extends TestCase
{
    public function test_allows_user_to_view_self(): void
    {
        $user = User::factory()->createOne();

        $this->assertTrue($this->policy()->view($user, $user));
    }

    public function test_denies_user_without_permission_to_view_other_user(): void
    {
        $user = User::factory()->createOne();
        $otherUser = User::factory()->createOne();

        $this->assertFalse($this->policy()->view($user, $otherUser));
    }

    public function test_allows_user_with_permission_to_view_other_user(): void
    {
        $user = User::factory()->createOne();
        $otherUser = User::factory()->createOne();

        $user->givePermissionTo(PermissionEnum::VIEW_USERS);

        $this->assertTrue($this->policy()->view($user, $otherUser));
    }

    protected function policy(): UserPolicy
    {
        return resolve(UserPolicy::class);
    }
}
