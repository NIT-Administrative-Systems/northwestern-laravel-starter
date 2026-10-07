<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Policies;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Models\User;
use App\Domains\User\Policies\UserPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UserPolicy::class)]
final class UserPolicyTest extends TestCase
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

        $user->givePermissionTo(SystemPermission::ViewUsers);

        $this->assertTrue($this->policy()->view($user, $otherUser));
    }

    public function test_view_any_denies_user_without_permission(): void
    {
        $user = User::factory()->createOne();

        $this->assertFalse($this->policy()->viewAny($user));
    }

    public function test_view_any_allows_user_with_permission(): void
    {
        $user = User::factory()->createOne();
        $user->givePermissionTo(SystemPermission::ViewUsers);

        $this->assertTrue($this->policy()->viewAny($user));
    }

    public function test_edit_allows_user_to_edit_self(): void
    {
        $user = User::factory()->createOne();

        $this->assertTrue($this->policy()->edit($user, $user));
    }

    public function test_edit_denies_user_without_permission_to_edit_other_user(): void
    {
        $user = User::factory()->createOne();
        $otherUser = User::factory()->createOne();

        $this->assertFalse($this->policy()->edit($user, $otherUser));
    }

    public function test_edit_allows_user_with_permission_to_edit_other_user(): void
    {
        $user = User::factory()->createOne();
        $otherUser = User::factory()->createOne();

        $user->givePermissionTo(SystemPermission::EditUsers);

        $this->assertTrue($this->policy()->edit($user, $otherUser));
    }

    protected function policy(): UserPolicy
    {
        return resolve(UserPolicy::class);
    }

    // An API user exists only to own service clients, so managing API access is enough to see one.
    public function test_someone_who_manages_api_access_sees_api_users_but_not_people(): void
    {
        $administrator = User::factory()->affiliate()->create();
        $administrator->givePermissionTo(SystemPermission::ManageApiAccess);
        $policy = resolve(UserPolicy::class);

        $this->assertTrue($policy->viewAny($administrator));
        $this->assertTrue($policy->view($administrator, User::factory()->api()->create()));
        $this->assertFalse($policy->view($administrator, User::factory()->create()));
    }
}
