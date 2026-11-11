<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Administration\Clusters\ApiCluster\Resources\ApiRequestLogs;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Api\Models\ApiRequestLog;
use App\Domains\User\Models\User;
use App\Filament\Administration\Clusters\ApiCluster\Resources\ApiRequestLogs\Pages\ListApiRequestLogs;
use App\Filament\Administration\Resources\Users\UserResource;
use App\Providers\Filament\AdministrationPanelProvider;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class ApiRequestLogResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['api.request_logging.enabled' => true]);
        Filament::setCurrentPanel(AdministrationPanelProvider::ID);
    }

    public function test_a_deleted_api_users_requests_still_list_and_link_to_their_record(): void
    {
        $apiUser = User::factory()->api()->create();
        $request = ApiRequestLog::factory()->for($apiUser)->create(['created_at' => now()]);
        $apiUser->delete();
        $this->actingAs($this->administrator());

        Livewire::test(ListApiRequestLogs::class)
            ->loadTable()
            ->assertCanSeeTableRecords([$request])
            ->assertTableColumnStateSet('user.username', $apiUser->username, $request)
            ->assertTableActionHasUrl('view', UserResource::getUrl('view', ['record' => $apiUser]), $request);
    }

    private function administrator(): User
    {
        $administrator = User::factory()->affiliate()->create();
        $administrator->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ViewApiRequestLogs);

        return $administrator;
    }
}
