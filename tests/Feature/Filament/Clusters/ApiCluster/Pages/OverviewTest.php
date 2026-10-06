<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Clusters\ApiCluster\Pages;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Models\User;
use App\Filament\Clusters\ApiCluster;
use App\Filament\Clusters\ApiCluster\Pages\Overview;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Overview::class)]
#[CoversClass(ApiCluster::class)]
final class OverviewTest extends TestCase
{
    public function test_api_administrators_and_request_log_viewers_can_open_it(): void
    {
        foreach ([SystemPermission::ManageApiAccess, SystemPermission::ViewApiRequestLogs] as $permission) {
            $this->actingAs($this->administrator($permission))
                ->get('/administration/api/overview')
                ->assertOk();
        }
    }

    // The cluster's own rule guards only its navigation, so the page must refuse on its own.
    public function test_other_administrators_are_refused(): void
    {
        $this->actingAs($this->administrator())
            ->get('/administration/api/overview')
            ->assertForbidden();
    }

    public function test_it_is_refused_when_the_api_is_off(): void
    {
        config(['api.enabled' => false]);

        $this->actingAs($this->administrator(SystemPermission::ManageApiAccess))
            ->get('/administration/api/overview')
            ->assertForbidden();
    }

    private function administrator(?SystemPermission $permission = null): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(array_filter([SystemPermission::AccessAdministrationPanel, $permission]));

        return $user;
    }
}
