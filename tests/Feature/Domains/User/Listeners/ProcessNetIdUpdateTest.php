<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Listeners;

use App\Domains\Auth\Enums\AuthTypeEnum;
use App\Domains\Auth\Enums\SystemRoleEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Events\NetIdUpdated;
use App\Domains\User\Listeners\ProcessNetIdUpdate;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ProcessNetIdUpdate::class)]
class ProcessNetIdUpdateTest extends TestCase
{
    private Role $adminRole;

    private Role $editorRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::factory()->create(['name' => 'admin']);
        $this->editorRole = Role::factory()->create(['name' => 'editor']);
    }

    public function test_it_removes_all_roles_except_northwestern_on_deactivate(): void
    {
        $user = User::factory()->create(['username' => 'abc123', 'auth_type' => AuthTypeEnum::SSO]);
        $user->assignRole([$this->adminRole, $this->editorRole, SystemRoleEnum::NORTHWESTERN_USER]);

        $event = new NetIdUpdated('netid=abc123&action=deactivate');
        $listener = new ProcessNetIdUpdate();
        $listener->handle($event);

        $user->refresh();

        $this->assertFalse($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('editor'));
        $this->assertTrue($user->hasRole(SystemRoleEnum::NORTHWESTERN_USER));
    }

    public function test_it_removes_all_roles_except_northwestern_on_deprovision(): void
    {
        $user = User::factory()->create(['username' => 'test123', 'auth_type' => AuthTypeEnum::SSO]);
        $user->assignRole([$this->adminRole, $this->editorRole, SystemRoleEnum::NORTHWESTERN_USER]);

        $event = new NetIdUpdated('netid=test123&action=deprovision');
        $listener = new ProcessNetIdUpdate();
        $listener->handle($event);

        $user->refresh();

        $this->assertFalse($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('editor'));
        $this->assertTrue($user->hasRole(SystemRoleEnum::NORTHWESTERN_USER));
    }

    public function test_it_removes_all_roles_except_northwestern_on_security_hold(): void
    {
        $user = User::factory()->create(['username' => 'sec123', 'auth_type' => AuthTypeEnum::SSO]);
        $user->assignRole([$this->adminRole, $this->editorRole, SystemRoleEnum::NORTHWESTERN_USER]);

        $event = new NetIdUpdated('netid=sec123&action=sechold');
        $listener = new ProcessNetIdUpdate();
        $listener->handle($event);

        $user->refresh();

        $this->assertFalse($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('editor'));
        $this->assertTrue($user->hasRole(SystemRoleEnum::NORTHWESTERN_USER));
    }

    public function test_it_marks_netid_as_inactive_on_deactivate(): void
    {
        $user = User::factory()->create([
            'username' => 'abc123',
            'auth_type' => AuthTypeEnum::SSO,
            'netid_inactive' => false,
        ]);

        $event = new NetIdUpdated('netid=abc123&action=deactivate');
        $listener = new ProcessNetIdUpdate();
        $listener->handle($event);

        $user->refresh();

        $this->assertTrue($user->netid_inactive);
    }

    public function test_it_marks_netid_as_inactive_on_deprovision(): void
    {
        $user = User::factory()->create([
            'username' => 'test123',
            'auth_type' => AuthTypeEnum::SSO,
            'netid_inactive' => false,
        ]);

        $event = new NetIdUpdated('netid=test123&action=deprovision');
        $listener = new ProcessNetIdUpdate();
        $listener->handle($event);

        $user->refresh();

        $this->assertTrue($user->netid_inactive);
    }

    public function test_it_marks_netid_as_inactive_on_security_hold(): void
    {
        $user = User::factory()->create([
            'username' => 'sec123',
            'auth_type' => AuthTypeEnum::SSO,
            'netid_inactive' => false,
        ]);

        $event = new NetIdUpdated('netid=sec123&action=sechold');
        $listener = new ProcessNetIdUpdate();
        $listener->handle($event);

        $user->refresh();

        $this->assertTrue($user->netid_inactive);
    }

    public function test_it_handles_user_with_only_northwestern_role(): void
    {
        $user = User::factory()->create(['username' => 'abc123', 'auth_type' => AuthTypeEnum::SSO]);
        $user->assignRole(SystemRoleEnum::NORTHWESTERN_USER);

        $event = new NetIdUpdated('netid=abc123&action=deactivate');
        $listener = new ProcessNetIdUpdate();
        $listener->handle($event);

        $user->refresh();

        $this->assertTrue($user->hasRole(SystemRoleEnum::NORTHWESTERN_USER));
        $this->assertCount(1, $user->roles);
    }

    public function test_it_does_nothing_when_netid_not_found(): void
    {
        $event = new NetIdUpdated('netid=nonexistent&action=deactivate');
        $listener = new ProcessNetIdUpdate();

        $listener->handle($event);

        /** @phpstan-ignore-next-line no-op, the listener just shouldn't throw an exception */
        $this->assertTrue(true);
    }

    public function test_it_handles_multiple_roles_being_removed(): void
    {
        $role1 = Role::factory()->create(['name' => 'role1']);
        $role2 = Role::factory()->create(['name' => 'role2']);
        $role3 = Role::factory()->create(['name' => 'role3']);

        $user = User::factory()->create(['username' => 'abc123', 'auth_type' => AuthTypeEnum::SSO]);
        $user->assignRole([$role1, $role2, $role3, SystemRoleEnum::NORTHWESTERN_USER]);

        $event = new NetIdUpdated('netid=abc123&action=deactivate');
        $listener = new ProcessNetIdUpdate();
        $listener->handle($event);

        $user->refresh();

        $this->assertFalse($user->hasRole('role1'));
        $this->assertFalse($user->hasRole('role2'));
        $this->assertFalse($user->hasRole('role3'));
        $this->assertTrue($user->hasRole(SystemRoleEnum::NORTHWESTERN_USER));
    }

    public function test_it_ignores_local_auth_users(): void
    {
        $user = User::factory()->create([
            'username' => 'local123',
            'auth_type' => AuthTypeEnum::LOCAL,
            'netid_inactive' => false,
        ]);
        $user->assignRole($this->adminRole);

        $event = new NetIdUpdated('netid=local123&action=deactivate');
        $listener = new ProcessNetIdUpdate();
        $listener->handle($event);

        $user->refresh();

        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->netid_inactive);
    }

    public function test_it_ignores_api_auth_users(): void
    {
        $user = User::factory()->create([
            'username' => 'api123',
            'auth_type' => AuthTypeEnum::API,
            'netid_inactive' => false,
        ]);
        $user->assignRole($this->adminRole);

        $event = new NetIdUpdated('netid=api123&action=deactivate');
        $listener = new ProcessNetIdUpdate();
        $listener->handle($event);

        $user->refresh();

        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->netid_inactive);
    }
}
