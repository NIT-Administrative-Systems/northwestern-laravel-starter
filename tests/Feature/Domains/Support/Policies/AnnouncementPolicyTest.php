<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Support\Policies;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Support\Models\Announcement;
use App\Domains\Support\Policies\AnnouncementPolicy;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AnnouncementPolicy::class)]
final class AnnouncementPolicyTest extends TestCase
{
    public function test_every_ability_needs_manage_announcements(): void
    {
        $announcement = Announcement::factory()->create();
        $manager = User::factory()->create();
        $manager->givePermissionTo(SystemPermission::ManageAnnouncements);
        $someone = User::factory()->create();

        foreach (['viewAny', 'create', 'deleteAny'] as $ability) {
            $this->assertTrue($manager->can($ability, Announcement::class), $ability);
            $this->assertFalse($someone->can($ability, Announcement::class), $ability);
        }

        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertTrue($manager->can($ability, $announcement), $ability);
            $this->assertFalse($someone->can($ability, $announcement), $ability);
        }
    }
}
