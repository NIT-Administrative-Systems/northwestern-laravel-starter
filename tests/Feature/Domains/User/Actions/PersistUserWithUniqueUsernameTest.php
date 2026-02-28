<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Actions;

use App\Domains\Auth\Enums\AuthTypeEnum;
use App\Domains\Auth\Enums\RoleModificationOriginEnum;
use App\Domains\Auth\Enums\SystemRoleEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Actions\PersistUserWithUniqueUsername;
use App\Domains\User\Models\Audit;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(PersistUserWithUniqueUsername::class)]
class PersistUserWithUniqueUsernameTest extends TestCase
{
    public function test_it_saves_user_if_username_is_unique(): void
    {
        $user = User::factory()->make([
            'username' => 'unique_user',
            'auth_type' => AuthTypeEnum::API,
        ]);

        $action = new PersistUserWithUniqueUsername();
        $savedUser = $action($user);

        $this->assertDatabaseHas('users', ['username' => 'unique_user']);
        $this->assertTrue($savedUser->exists);
    }

    public function test_it_assigns_default_role_for_sso_user(): void
    {
        $user = User::factory()->make([
            'username' => 'sso_user',
            'auth_type' => AuthTypeEnum::SSO,
        ]);

        $action = new PersistUserWithUniqueUsername();
        $savedUser = $action($user);

        $this->assertTrue($savedUser->hasRole(SystemRoleEnum::NORTHWESTERN_USER));
    }

    public function test_it_skips_role_assignment_when_sso_user_already_has_role(): void
    {
        $user = User::factory()->create([
            'username' => 'existing_sso_user',
            'auth_type' => AuthTypeEnum::SSO,
        ]);

        $role = Role::query()->where('name', SystemRoleEnum::NORTHWESTERN_USER->value)->firstOrFail();
        $user->assignRoleWithAudit($role, RoleModificationOriginEnum::SSO_PROVISIONING);

        $auditCountBefore = Audit::query()->where('user_id', $user->id)->count();

        $action = new PersistUserWithUniqueUsername();
        $action($user);

        $auditCountAfter = Audit::query()->where('user_id', $user->id)->count();

        $this->assertTrue($user->hasRole(SystemRoleEnum::NORTHWESTERN_USER));
        $this->assertSame($auditCountBefore, $auditCountAfter, 'No new audit records should be created for an already-assigned role');
    }

    public function test_it_returns_existing_user_when_username_conflicts(): void
    {
        $existingUser = User::factory()->api()->create([
            'username' => 'duplicate_user',
        ]);

        $duplicateUser = User::factory()->api()->make([
            'username' => 'duplicate_user',
        ]);

        $action = new PersistUserWithUniqueUsername();
        $savedUser = $action($duplicateUser);

        $this->assertSame($existingUser->id, $savedUser->id);
        $this->assertSame(1, User::query()->where('username', 'duplicate_user')->count());
    }
}
