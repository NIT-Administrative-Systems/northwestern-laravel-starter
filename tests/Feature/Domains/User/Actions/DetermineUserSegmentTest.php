<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Actions;

use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Actions\DetermineUserSegment;
use App\Domains\User\Enums\UserSegmentEnum;
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
            ->hasPermissions([PermissionEnum::MANAGE_ALL])
            ->create();

        $user = User::factory()->create();

        $user->assignRole($systemManagedRole);

        $segment = $this->action()($user);

        $this->assertEquals(UserSegmentEnum::SUPER_ADMIN, $segment);
    }

    public function test_determines_external_user(): void
    {
        $user = User::factory()->affiliate()->create();

        $segment = $this->action()($user);

        $this->assertEquals(UserSegmentEnum::EXTERNAL_USER, $segment);
    }

    public function test_determines_other(): void
    {
        $user = User::factory()->create();

        $segment = $this->action()($user);

        $this->assertEquals(UserSegmentEnum::OTHER, $segment);
    }

    protected function action(): DetermineUserSegment
    {
        return resolve(DetermineUserSegment::class);
    }
}
