<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Core\Policies;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Core\Models\Audit;
use App\Domains\Core\Policies\AuditPolicy;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AuditPolicy::class)]
final class AuditPolicyTest extends TestCase
{
    public function test_it_is_the_policy_for_audits(): void
    {
        $this->assertInstanceOf(AuditPolicy::class, Gate::getPolicyFor(Audit::class));
    }

    public function test_view_any_denies_user_without_permission(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->policy()->viewAny($user));
    }

    public function test_view_any_allows_user_with_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::ViewAuditLogs);

        $this->assertTrue($this->policy()->viewAny($user));
    }

    public function test_view_follows_view_any(): void
    {
        $user = User::factory()->create();
        $audit = Audit::query()->latest('id')->firstOrFail();

        $this->assertFalse($this->policy()->view($user, $audit));

        $user->givePermissionTo(SystemPermission::ViewAuditLogs);

        $this->assertTrue($this->policy()->view($user, $audit));
    }

    protected function policy(): AuditPolicy
    {
        return resolve(AuditPolicy::class);
    }
}
