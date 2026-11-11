<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Administration\Pages\Platform;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\User\Models\User;
use App\Filament\Administration\Resources\Users\UserResource;
use App\Providers\Filament\AdministrationPanelProvider;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class OverviewTest extends TestCase
{
    public function test_it_is_the_administration_panels_home(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ManageAll);

        $this->actingAs($admin)
            ->get('/administration')
            ->assertOk()
            ->assertSee('Platform configuration and system health')
            ->assertDontSee('Replace this with');
    }

    public function test_someone_who_cannot_see_it_starts_on_the_first_page_they_can(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ViewUsers);

        $this->actingAs($admin)
            ->get('/administration')
            ->assertRedirect(UserResource::getUrl(panel: AdministrationPanelProvider::ID));
    }
}
