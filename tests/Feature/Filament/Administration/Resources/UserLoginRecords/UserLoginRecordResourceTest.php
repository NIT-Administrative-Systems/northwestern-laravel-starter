<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Administration\Resources\UserLoginRecords;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginRecord;
use App\Filament\Administration\Resources\UserLoginRecords\Pages\ListUserLoginRecords;
use App\Filament\Administration\Resources\Users\UserResource;
use App\Providers\Filament\AdministrationPanelProvider;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Feature\Filament\Administration\Concerns\AssertsLinkedRows;
use Tests\TestCase;

#[CoversNothing]
final class UserLoginRecordResourceTest extends TestCase
{
    use AssertsLinkedRows;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AdministrationPanelProvider::ID);
    }

    public function test_a_deactivated_persons_sign_ins_still_list_and_link_to_their_record(): void
    {
        $person = User::factory()->create();
        $record = UserLoginRecord::factory()->for($person)->create();
        $person->delete();
        $this->actingAs($this->administrator());

        Livewire::test(ListUserLoginRecords::class)
            ->loadTable()
            ->assertCanSeeTableRecords([$record])
            ->assertTableColumnStateSet('user.username', $person->username, $record)
            ->assertTableActionHasUrl('view', UserResource::getUrl('view', ['record' => $person]), $record);
    }

    public function test_a_sign_in_with_no_request_details_has_no_empty_links(): void
    {
        $record = UserLoginRecord::factory()->create(['ip_address' => null, 'user_agent' => null]);
        $record->user->delete();
        $this->actingAs($this->administrator());

        $this->assertEveryEmptyCellHasPlaceholder(Livewire::test(ListUserLoginRecords::class)->loadTable(), $record);
    }

    private function administrator(): User
    {
        $administrator = User::factory()->affiliate()->create();
        $administrator->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ViewLoginRecords);

        return $administrator;
    }
}
