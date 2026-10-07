<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Administration\Resources\Users\Actions;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Enums\TokenExpiration;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\User\Models\User;
use App\Filament\Administration\Resources\Users\Pages\ListUsers;
use App\Filament\Support\RevealOnceSecret;
use App\Providers\Filament\AdministrationPanelProvider;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class CreateApiUserActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AdministrationPanelProvider::ID);

        $admin = User::factory()->create();
        $admin->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ViewUsers, SystemPermission::ManageApiAccess);
        $this->actingAs($admin);
    }

    public function test_it_creates_an_api_user_with_its_first_client(): void
    {
        $component = Livewire::test(ListUsers::class)
            ->mountAction(TestAction::make('createApiUser'))
            ->fillForm([
                'first_name' => 'Reporting',
                'username' => 'reporting',
                'email' => 'team@example.edu',
                'name' => 'Production',
                'expiration' => TokenExpiration::ThreeMonths->value,
            ])
            ->goToWizardStep(2)
            ->goToWizardStep(3)
            ->assertHasNoFormErrors();

        $user = User::query()->where('username', 'api-reporting')->sole();
        $this->assertSame(AuthType::API, $user->auth_type);
        $client = OAuthClient::query()->whereMorphedTo('owner', $user)->sole();
        $this->assertSame($client->getKey(), RevealOnceSecret::for('api_user:create')->identifier());

        $component->callMountedAction()->assertRedirect();

        $this->assertFalse(RevealOnceSecret::for('api_user:create')->issued());
    }

    public function test_it_is_hidden_without_manage_api_access(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ViewUsers);
        $this->actingAs($viewer);

        Livewire::test(ListUsers::class)->assertActionHidden(TestAction::make('createApiUser'));
    }
}
