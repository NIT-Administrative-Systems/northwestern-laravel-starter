<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Actions\Lifecycle;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Core\Enums\AuditEvent;
use App\Domains\Core\Models\Audit;
use App\Domains\User\Actions\Lifecycle\DeactivateUser;
use App\Domains\User\Actions\Lifecycle\ReactivateUser;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\IssuesPersonalAccessTokens;
use Tests\TestCase;

#[CoversClass(ReactivateUser::class)]
final class ReactivateUserTest extends TestCase
{
    use IssuesPersonalAccessTokens;

    public function test_it_restores_the_account_and_leaves_its_credentials_revoked(): void
    {
        $administrator = $this->administrator();
        $person = User::factory()->create();
        [, $token] = $this->issuePersonalAccessToken($person);
        $this->actingAs($administrator);
        resolve(DeactivateUser::class)($person, $administrator);

        resolve(ReactivateUser::class)($person, $administrator);

        $this->assertNotSoftDeleted($person);
        $this->assertTrue((bool) $token->fresh()?->revoked);
        $audit = Audit::query()->where('auditable_id', $person->getKey())->where('event', AuditEvent::AccountReactivated->value)->sole();
        $this->assertSame($administrator->getKey(), $audit->user_id);
        $this->assertSame(['deleted_at' => null], $audit->new_values);
        $this->assertFalse(Audit::query()->where('auditable_id', $person->getKey())->where('event', AuditEvent::Restored->value)->exists());
    }

    public function test_an_active_account_is_left_alone(): void
    {
        $administrator = $this->administrator();
        $person = User::factory()->create();

        resolve(ReactivateUser::class)($person, $administrator);

        $this->assertFalse(Audit::query()->where('auditable_id', $person->getKey())->where('event', AuditEvent::AccountReactivated->value)->exists());
    }

    public function test_it_refuses_someone_without_edit_users(): void
    {
        $person = User::factory()->create();
        $person->delete();

        $this->expectException(AuthorizationException::class);

        try {
            resolve(ReactivateUser::class)($person, User::factory()->create());
        } finally {
            $this->assertSoftDeleted($person);
        }
    }

    private function administrator(): User
    {
        $administrator = User::factory()->affiliate()->create();
        $administrator->givePermissionTo(SystemPermission::EditUsers);

        return $administrator;
    }
}
