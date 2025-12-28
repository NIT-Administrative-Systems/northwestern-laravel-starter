<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Policies;

use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\User\Models\User;
use App\Domains\User\Policies\AuditPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AuditPolicy::class)]
class AuditPolicyTest extends TestCase
{
    public function test_view_any_denies_user_without_permission(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->policy()->viewAny($user));
    }

    public function test_view_any_allows_user_with_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(PermissionEnum::VIEW_AUDIT_LOGS);

        $this->assertTrue($this->policy()->viewAny($user));
    }

    protected function policy(): AuditPolicy
    {
        return resolve(AuditPolicy::class);
    }
}
