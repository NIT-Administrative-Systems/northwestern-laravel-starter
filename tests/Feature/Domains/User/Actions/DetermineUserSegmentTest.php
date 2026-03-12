<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Actions;

use App\Domains\Auth\Enums\RoleModificationOrigin;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Actions\DetermineUserSegment;
use App\Domains\User\Enums\UserSegment;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(DetermineUserSegment::class)]
class DetermineUserSegmentTest extends TestCase
{
    public function test_determines_super_admin(): void
    {
        $systemManagedRole = Role::factory()
            ->systemManaged()
            ->hasPermissions([SystemPermission::ManageAll])
            ->create();

        $user = User::factory()->create();

        $user->assignRoleWithAudit($systemManagedRole, RoleModificationOrigin::System);

        $segment = $this->action()($user);

        $this->assertEquals(UserSegment::SuperAdmin, $segment);
    }

    public function test_determines_external_user(): void
    {
        $user = User::factory()->affiliate()->create();

        $segment = $this->action()($user);

        $this->assertEquals(UserSegment::ExternalUser, $segment);
    }

    public function test_determines_other(): void
    {
        $user = User::factory()->create();

        $segment = $this->action()($user);

        $this->assertEquals(UserSegment::Other, $segment);
    }

    protected function action(): DetermineUserSegment
    {
        return resolve(DetermineUserSegment::class);
    }
}
