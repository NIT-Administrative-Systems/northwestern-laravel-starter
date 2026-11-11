<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\User\AccountStatusAccess;
use App\Domains\User\Models\User;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AccountStatusAccess::class)]
final class AccountStatusAccessTest extends TestCase
{
    public function test_edit_users_may_change_someone_elses_account(): void
    {
        $administrator = User::factory()->affiliate()->create();
        $administrator->givePermissionTo(SystemPermission::EditUsers);

        $this->assertTrue($this->access()->decide($administrator, User::factory()->create())->allowed());
    }

    public function test_someone_without_edit_users_may_not(): void
    {
        $decision = $this->access()->decide(User::factory()->create(), User::factory()->create());

        $this->assertTrue($decision->denied());
        $this->assertSame('You don\'t have permission to change the status of accounts.', $decision->message());
    }

    // Manage All passes every policy through Gate::before, so this isn't a policy.
    public function test_not_even_a_super_administrator_changes_their_own_account(): void
    {
        $superAdministrator = User::factory()->affiliate()->create();
        $superAdministrator->givePermissionTo(SystemPermission::ManageAll);

        $decision = $this->access()->decide($superAdministrator, $superAdministrator);

        $this->assertTrue($decision->denied());
        $this->assertSame('You can\'t change the status of your own account.', $decision->message());
    }

    public function test_nobody_changes_an_account_while_impersonating(): void
    {
        $superAdministrator = User::factory()->affiliate()->create();
        $superAdministrator->givePermissionTo(SystemPermission::ManageAll);
        $this->impersonating();

        $decision = $this->access()->decide($superAdministrator, User::factory()->create());

        $this->assertTrue($decision->denied());
        $this->assertSame('Stop impersonating to change the status of an account.', $decision->message());
    }

    public function test_the_system_may(): void
    {
        $this->assertTrue($this->access()->decide(null, User::factory()->create())->allowed());
    }

    private function access(): AccountStatusAccess
    {
        return resolve(AccountStatusAccess::class);
    }

    private function impersonating(): void
    {
        $impersonate = Mockery::mock();
        $impersonate->shouldReceive('isImpersonating')->andReturn(true);
        $impersonate->shouldReceive('getImpersonatorId')->andReturn(null);

        $this->app->instance('impersonate', $impersonate);
    }
}
