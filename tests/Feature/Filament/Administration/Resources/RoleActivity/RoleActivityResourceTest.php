<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Administration\Resources\RoleActivity;

use App\Domains\Access\Enums\RoleModificationOrigin;
use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Models\Role;
use App\Domains\Core\Models\Audit;
use App\Domains\User\Models\User;
use App\Filament\Administration\Resources\RoleActivity\Pages\ListRoleActivity;
use App\Providers\Filament\AdministrationPanelProvider;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class RoleActivityResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AdministrationPanelProvider::ID);
    }

    // Each audit lists every role the person had before and after, not only the one that changed.
    public function test_the_role_filter_finds_changes_to_that_role_only(): void
    {
        $faculty = Role::factory()->create(['name' => 'Faculty/Staff']);
        $editor = Role::factory()->create(['name' => 'Editor']);
        $person = User::factory()->affiliate()->create();

        $person->assignRoleWithAudit($faculty, RoleModificationOrigin::UiAction);
        $facultyAssigned = $this->latestRoleChange();
        $person->assignRoleWithAudit($editor, RoleModificationOrigin::UiAction);
        $editorAssigned = $this->latestRoleChange();
        $person->removeRoleWithAudit($faculty, RoleModificationOrigin::UiAction);
        $facultyRemoved = $this->latestRoleChange();

        $this->actingAs($this->administrator());

        Livewire::test(ListRoleActivity::class)
            ->loadTable()
            ->filterTable('role', $faculty->getKey())
            ->assertCanSeeTableRecords([$facultyAssigned, $facultyRemoved])
            ->assertCanNotSeeTableRecords([$editorAssigned])
            ->filterTable('role', $editor->getKey())
            ->assertCanSeeTableRecords([$editorAssigned])
            ->assertCanNotSeeTableRecords([$facultyAssigned, $facultyRemoved]);
    }

    private function latestRoleChange(): Audit
    {
        return Audit::query()->roleActivity()->latest('id')->firstOrFail();
    }

    private function administrator(): User
    {
        $administrator = User::factory()->affiliate()->create();
        $administrator->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ViewAuditLogs);

        return $administrator;
    }
}
