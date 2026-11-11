<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Actions\Lifecycle;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Core\Enums\AuditEvent;
use App\Domains\Core\Models\Audit;
use App\Domains\User\Actions\Lifecycle\DeactivateUser;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\IssuesPersonalAccessTokens;
use Tests\TestCase;

#[CoversClass(DeactivateUser::class)]
final class DeactivateUserTest extends TestCase
{
    use IssuesPersonalAccessTokens;

    public function test_it_soft_deletes_the_account_revokes_its_credentials_and_signs_it_out(): void
    {
        config(['session.driver' => 'database']);
        $administrator = $this->administrator();
        $person = User::factory()->create();
        [, $token] = $this->personalAccessToken($person);
        DB::table('sessions')->insert(['id' => 'theirs', 'user_id' => $person->getKey(), 'payload' => '', 'last_activity' => now()->getTimestamp()]);
        $this->actingAs($administrator);

        resolve(DeactivateUser::class)($person, $administrator);

        $this->assertSoftDeleted($person);
        $this->assertTrue((bool) $token->fresh()?->revoked);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $person->getKey())->count());
    }

    public function test_it_is_audited_as_a_deactivation_by_the_administrator(): void
    {
        $administrator = $this->administrator();
        $person = User::factory()->create();
        $this->actingAs($administrator);

        resolve(DeactivateUser::class)($person, $administrator);

        $audit = Audit::query()->where('auditable_id', $person->getKey())->where('event', AuditEvent::AccountDeactivated->value)->sole();
        $this->assertSame($administrator->getKey(), $audit->user_id);
        $this->assertSame(['deleted_at' => null], $audit->old_values);
        $this->assertFalse(Audit::query()->where('auditable_id', $person->getKey())->where('event', AuditEvent::Deleted->value)->exists());
    }

    // The scheduler runs in the console, where model events aren't audited.
    public function test_the_system_records_why_it_deactivated_an_account(): void
    {
        $person = User::factory()->create();

        resolve(DeactivateUser::class)($person, null, ['reason' => 'no sign-in for 90 days']);

        $audit = Audit::query()->where('auditable_id', $person->getKey())->where('event', AuditEvent::AccountDeactivated->value)->sole();
        $this->assertNull($audit->user_id);
        $this->assertSame('no sign-in for 90 days', str($audit->tags)->after('reason: ')->toString());
    }

    public function test_an_account_already_deactivated_is_left_alone(): void
    {
        $person = User::factory()->create();
        $person->delete();

        resolve(DeactivateUser::class)($person, null);

        $this->assertFalse(Audit::query()->where('auditable_id', $person->getKey())->where('event', AuditEvent::AccountDeactivated->value)->exists());
    }

    public function test_it_refuses_what_account_status_access_refuses(): void
    {
        $administrator = $this->administrator();

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessageIsOrContains('You can\'t change the status of your own account.');

        try {
            resolve(DeactivateUser::class)($administrator, $administrator);
        } finally {
            $this->assertNotSoftDeleted($administrator);
        }
    }

    private function administrator(): User
    {
        $administrator = User::factory()->affiliate()->create();
        $administrator->givePermissionTo(SystemPermission::EditUsers);

        return $administrator;
    }
}
