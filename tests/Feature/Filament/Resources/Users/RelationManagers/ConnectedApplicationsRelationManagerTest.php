<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources\Users\RelationManagers;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\OAuthConnection;
use App\Domains\User\Models\Audit;
use App\Domains\User\Models\User;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\ConnectedApplicationsRelationManager;
use App\Providers\Filament\AdministrationPanelProvider;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\RunsAuthorizationCodeFlow;
use Tests\TestCase;

#[CoversClass(ConnectedApplicationsRelationManager::class)]
final class ConnectedApplicationsRelationManagerTest extends TestCase
{
    use RunsAuthorizationCodeFlow;

    public function test_an_administrator_disconnects_a_persons_application(): void
    {
        $person = User::factory()->create();
        $this->actingAs($person);
        $client = $this->registerApplication();
        [, $verifier] = $this->requestAuthorization($client);
        $this->exchange($client, $this->approve($client), $verifier);
        $connection = OAuthConnection::query()->sole();

        Filament::setCurrentPanel(AdministrationPanelProvider::ID);
        $admin = User::factory()->create();
        $admin->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ManageApiAccess);
        $this->actingAs($admin);

        $this->assertTrue(ConnectedApplicationsRelationManager::canViewForRecord($person, ViewUser::class));

        Livewire::test(ConnectedApplicationsRelationManager::class, ['ownerRecord' => $person, 'pageClass' => ViewUser::class])
            ->assertCanSeeTableRecords([$connection])
            ->callAction(TestAction::make('disconnect')->table($connection));

        $this->assertSame(0, OAuthConnection::query()->count());
        $this->assertTrue(Audit::query()->where('event', 'oauth_application_disconnected')->where('auditable_id', $person->getKey())->exists());
    }

    public function test_it_needs_manage_api_access(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertFalse(ConnectedApplicationsRelationManager::canViewForRecord(User::factory()->create(), ViewUser::class));
    }
}
