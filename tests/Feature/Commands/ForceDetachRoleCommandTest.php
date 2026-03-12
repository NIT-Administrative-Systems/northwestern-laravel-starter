<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Domains\Auth\Enums\RoleModificationOrigin;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;
use Tests\TestCase;

class ForceDetachRoleCommandTest extends TestCase
{
    public function test_fails_when_user_not_found(): void
    {
        $this->artisan('role:force-detach', [
            'user' => 'nonexistent-user',
            'role' => 'any-role',
            '--reason' => 'Testing',
            '--force' => true,
        ])
            ->expectsOutputToContain('User not found')
            ->assertFailed();
    }

    public function test_fails_when_role_not_found(): void
    {
        $user = User::factory()->create();

        $this->artisan('role:force-detach', [
            'user' => $user->username,
            'role' => 'nonexistent-role',
            '--reason' => 'Testing',
            '--force' => true,
        ])
            ->expectsOutputToContain('Role not found')
            ->assertFailed();
    }

    public function test_fails_when_user_does_not_have_the_role(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();

        $this->artisan('role:force-detach', [
            'user' => $user->username,
            'role' => $role->name,
            '--reason' => 'Testing',
            '--force' => true,
        ])
            ->expectsOutputToContain('does not have the')
            ->assertFailed();
    }

    public function test_cancellation_exits_successfully_without_detaching(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $user->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $this->artisan('role:force-detach', [
            'user' => $user->username,
            'role' => $role->name,
        ])
            ->expectsQuestion('Reason for this emergency detachment', 'Testing cancellation')
            ->expectsConfirmation('Do you want to proceed?', 'no')
            ->expectsOutputToContain('Cancelled')
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->hasRole($role));
    }

    public function test_detaches_role_when_confirmed_interactively(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $user->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $this->artisan('role:force-detach', [
            'user' => $user->username,
            'role' => $role->name,
        ])
            ->expectsQuestion('Reason for this emergency detachment', 'Emergency access revocation')
            ->expectsConfirmation('Do you want to proceed?', 'yes')
            ->expectsOutputToContain('detached from user')
            ->assertSuccessful();

        $this->assertFalse($user->fresh()->hasRole($role));
    }

    public function test_force_flag_skips_confirmation(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $user->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $this->artisan('role:force-detach', [
            'user' => $user->username,
            'role' => $role->name,
            '--reason' => 'Vapor emergency detach',
            '--force' => true,
        ])
            ->expectsOutputToContain('detached from user')
            ->assertSuccessful();

        $this->assertFalse($user->fresh()->hasRole($role));
    }

    public function test_force_flag_requires_reason_option(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $user->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $this->artisan('role:force-detach', [
            'user' => $user->username,
            'role' => $role->name,
            '--force' => true,
        ])
            ->expectsOutputToContain('--reason option is required')
            ->assertFailed();

        $this->assertTrue($user->fresh()->hasRole($role));
    }

    public function test_finds_user_by_id(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $user->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $this->artisan('role:force-detach', [
            'user' => (string) $user->id,
            'role' => $role->name,
            '--reason' => 'Testing by ID',
            '--force' => true,
        ])
            ->assertSuccessful();

        $this->assertFalse($user->fresh()->hasRole($role));
    }

    public function test_detaches_assignment_locked_role(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->assignmentLocked()->create();
        $user->assignRoleWithAudit($role, RoleModificationOrigin::System);

        $this->artisan('role:force-detach', [
            'user' => $user->username,
            'role' => $role->name,
            '--reason' => 'Emergency override of locked role',
            '--force' => true,
        ])
            ->assertSuccessful();

        $this->assertFalse($user->fresh()->hasRole($role));
    }
}
