<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Administration\Resources\Audits;

use App\Domains\Access\Enums\RoleModificationOrigin;
use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Models\Role;
use App\Domains\Core\Models\Audit;
use App\Domains\User\Models\User;
use App\Filament\Administration\Resources\Audits\AuditResource;
use App\Filament\Administration\Resources\Audits\Pages\ListAudits;
use App\Filament\Administration\Resources\RoleActivity\Pages\ListRoleActivity;
use App\Filament\Administration\Resources\RoleActivity\RoleActivityResource;
use App\Providers\Filament\AdministrationPanelProvider;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversNothing]
final class AuditResourceTest extends TestCase
{
    /** @return \Iterator<string, array{string}> */
    public static function auditPages(): \Iterator
    {
        yield 'audit log' => ['/administration/audits'];
        yield 'role activity' => ['/administration/roles/activity'];
    }

    #[DataProvider('auditPages')]
    public function test_an_administrator_without_view_audit_logs_is_refused(string $url): void
    {
        $this->actingAs($this->administrator())->get($url)->assertForbidden();
    }

    #[DataProvider('auditPages')]
    public function test_an_administrator_with_view_audit_logs_sees_the_page(string $url): void
    {
        $this->actingAs($this->administrator(SystemPermission::ViewAuditLogs))->get($url)->assertOk();
    }

    public function test_one_audit_needs_view_audit_logs(): void
    {
        $audit = Audit::query()->latest('id')->firstOrFail();

        $this->actingAs($this->administrator())->get(AuditResource::getUrl('view', ['record' => $audit], panel: 'administration'))->assertForbidden();
        $this->actingAs($this->administrator(SystemPermission::ViewAuditLogs))->get(AuditResource::getUrl('view', ['record' => $audit], panel: 'administration'))->assertOk();
    }

    public function test_both_resources_decide_access_by_the_audit_policy(): void
    {
        $this->actingAs($this->administrator());

        $this->assertFalse(AuditResource::canAccess());
        $this->assertFalse(RoleActivityResource::canAccess());

        $this->actingAs($this->administrator(SystemPermission::ViewAuditLogs));

        $this->assertTrue(AuditResource::canAccess());
        $this->assertTrue(RoleActivityResource::canAccess());
    }

    public function test_a_change_by_someone_since_deactivated_is_still_theirs(): void
    {
        $changer = User::factory()->create();
        $this->actingAs($changer);
        User::factory()->create()->assignRoleWithAudit(Role::factory()->create(), RoleModificationOrigin::UiAction);
        $audit = Audit::query()->roleActivity()->latest('id')->firstOrFail();
        $changer->delete();

        $this->actingAs($this->administrator(SystemPermission::ViewAuditLogs));
        Filament::setCurrentPanel(AdministrationPanelProvider::ID);

        Livewire::test(ListAudits::class)
            ->loadTable()
            ->assertTableColumnStateSet('user.username', $changer->username, $audit);

        Livewire::test(ListRoleActivity::class)
            ->loadTable()
            ->assertTableColumnStateSet('user.full_name', $changer->full_name, $audit);
    }

    private function administrator(SystemPermission ...$permissions): User
    {
        $administrator = User::factory()->create();
        $administrator->givePermissionTo(SystemPermission::AccessAdministrationPanel, ...$permissions);

        return $administrator;
    }
}
