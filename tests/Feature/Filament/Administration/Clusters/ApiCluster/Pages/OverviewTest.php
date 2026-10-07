<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Administration\Clusters\ApiCluster\Pages;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
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

    // API administrators keep the overview while the API is off, to see and revoke what's left.
    public function test_only_api_administrators_open_it_while_the_api_is_off(): void
    {
        config(['api.enabled' => false]);

        $this->actingAs($this->administrator(SystemPermission::ManageApiAccess))
            ->get('/administration/api/overview')
            ->assertOk();

        $this->actingAs($this->administrator(SystemPermission::ViewApiRequestLogs))
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
