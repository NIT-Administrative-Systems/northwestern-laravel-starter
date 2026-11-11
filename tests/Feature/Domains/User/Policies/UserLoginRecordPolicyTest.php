<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Policies;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginRecord;
use App\Domains\User\Policies\UserLoginRecordPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UserLoginRecordPolicy::class)]
final class UserLoginRecordPolicyTest extends TestCase
{
    public function test_view_any_denies_user_without_permission(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->policy()->viewAny($user));
    }

    public function test_view_any_allows_user_with_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::ViewLoginRecords);

        $this->assertTrue($this->policy()->viewAny($user));
    }

    public function test_view_follows_view_any(): void
    {
        $user = User::factory()->create();
        $record = UserLoginRecord::factory()->create();

        $this->assertFalse($this->policy()->view($user, $record));

        $user->givePermissionTo(SystemPermission::ViewLoginRecords);

        $this->assertTrue($this->policy()->view($user, $record));
    }

    protected function policy(): UserLoginRecordPolicy
    {
        return resolve(UserLoginRecordPolicy::class);
    }
}
