<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Administration\Resources\Users\RelationManagers;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Api\Actions\ServiceClients\CreateServiceClient;
use App\Domains\Api\Enums\CredentialStatus;
use App\Domains\Api\Enums\TokenExpiration;
use App\Domains\Api\Models\OAuthClient;
use App\Domains\User\Models\User;
use App\Filament\Administration\Resources\Users\Pages\ViewUser;
use App\Filament\Administration\Resources\Users\RelationManagers\ServiceClientsRelationManager;
use App\Filament\Support\RevealOnceSecret;
use App\Providers\Filament\AdministrationPanelProvider;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class ServiceClientsRelationManagerTest extends TestCase
{
    private User $apiUser;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AdministrationPanelProvider::ID);

        $admin = User::factory()->create();
        $admin->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ManageApiAccess);
        $this->actingAs($admin);

        $this->apiUser = User::factory()->api()->create();
    }

    public function test_it_shows_only_for_api_users_to_holders_of_manage_api_access(): void
    {
        $this->assertTrue(ServiceClientsRelationManager::canViewForRecord($this->apiUser, ViewUser::class));
        $this->assertFalse(ServiceClientsRelationManager::canViewForRecord(User::factory()->create(), ViewUser::class));

        $this->actingAs(User::factory()->create());
        $this->assertFalse(ServiceClientsRelationManager::canViewForRecord($this->apiUser, ViewUser::class));
    }

    public function test_it_lists_the_api_users_clients(): void
    {
        $client = $this->client('Nightly sync');

        $this->relationManager()
            ->assertCanSeeTableRecords([$client])
            ->assertSee('Nightly sync');
    }

    // A quiet menu, as on Applications, rather than a filled Actions button on every row.
    public function test_a_clients_actions_are_in_an_icon_menu(): void
    {
        $this->client('Nightly sync');

        $html = $this->relationManager()->html();

        $this->assertStringContainsString('fi-ta-actions', $html);
        $this->assertMatchesRegularExpression('/fi-icon-btn[^>]*aria-label="Actions"|aria-label="Actions"[^>]*fi-icon-btn/s', $html);
        $this->assertDoesNotMatchRegularExpression('/<button[^>]*fi-btn[^>]*>\s*(<[^>]+>\s*)*Actions\s*</s', $html);
    }

    // The secret is shown once, kept only encrypted in the session until the operator confirms.
    public function test_creating_a_client_shows_its_secret_once(): void
    {
        $component = $this->relationManager()
            ->mountTableAction('createServiceClient')
            ->fillForm(['name' => 'Production sync', 'expiration' => TokenExpiration::ThreeMonths->value, 'allowed_ips' => ['10.0.0.0/8']])
            ->goToNextWizardStep();

        /** @var OAuthClient $client */
        $client = OAuthClient::query()->whereMorphedTo('owner', $this->apiUser)->sole();
        $creation = RevealOnceSecret::for('service_client:create');

        $this->assertSame('Production sync', $client->name);
        $this->assertSame(['10.0.0.0/8'], $client->allowed_ips);
        $this->assertSame($client->getKey(), $creation->identifier($component->instance()));
        $this->assertTrue(Hash::check((string) $creation->secret($component->instance()), (string) $client->secret));

        $component->callMountedTableAction();

        $this->assertSame([], preg_grep('/^reveal_once_secret:/', array_keys(Session::all())));
    }

    // Each run keeps its own secret: one abandoned or still open in another browser tab neither
    // stops the next from issuing its client nor shows it that run's client.
    public function test_wizards_open_in_two_tabs_each_create_and_show_their_own_client(): void
    {
        $otherApiUser = User::factory()->api()->create();
        $creation = RevealOnceSecret::for('service_client:create');

        $firstTab = $this->relationManager()
            ->mountTableAction('createServiceClient')
            ->fillForm(['name' => 'First tab', 'expiration' => TokenExpiration::ThreeMonths->value]);

        $secondTab = $this->relationManager($otherApiUser)
            ->mountTableAction('createServiceClient')
            ->fillForm(['name' => 'Second tab', 'expiration' => TokenExpiration::ThreeMonths->value])
            ->goToNextWizardStep();

        $firstTab->goToNextWizardStep();

        $first = OAuthClient::query()->whereMorphedTo('owner', $this->apiUser)->sole();
        $second = OAuthClient::query()->whereMorphedTo('owner', $otherApiUser)->sole();
        $this->assertSame($first->getKey(), $creation->identifier($firstTab->instance()));
        $this->assertSame($second->getKey(), $creation->identifier($secondTab->instance()));

        // Finishing one run leaves the other's secret.
        $firstTab->callMountedTableAction();

        $this->assertSame($second->getKey(), RevealOnceSecret::for('service_client:create')->identifier($secondTab->instance()));
    }

    public function test_rotating_adds_a_replacement_and_keeps_the_client(): void
    {
        $client = $this->client('Sync');

        $this->relationManager()
            ->mountTableAction('rotateServiceClient', $client)
            ->fillForm(['name' => 'Sync 2026', 'expiration' => TokenExpiration::OneYear->value])
            ->goToNextWizardStep()
            ->callMountedTableAction();

        $replacement = OAuthClient::query()->where('rotated_from_client_id', $client->getKey())->sole();
        $this->assertSame('Sync 2026', $replacement->name);
        $this->assertSame(CredentialStatus::Active, $client->fresh()?->status);
    }

    public function test_ip_restrictions_can_be_edited(): void
    {
        $client = $this->client('Sync');

        $this->relationManager()
            ->callAction(TestAction::make('editServiceClientIpRestrictions')->table($client), ['allowed_ips' => ['192.0.2.0/24']])
            ->assertHasNoFormErrors();

        $this->assertSame(['192.0.2.0/24'], $client->fresh()?->allowed_ips);
    }

    public function test_revoking_stops_the_client(): void
    {
        $client = $this->client('Sync');

        $this->relationManager()->callAction(TestAction::make('revokeServiceClient')->table($client));

        $this->assertSame(CredentialStatus::Revoked, $client->fresh()?->status);
    }

    private function client(string $name): OAuthClient
    {
        [, $client] = resolve(CreateServiceClient::class)($this->apiUser, $name, now()->addDays(30));

        return $client;
    }

    /** @return Testable<ServiceClientsRelationManager> */
    private function relationManager(?User $apiUser = null): Testable
    {
        return Livewire::test(ServiceClientsRelationManager::class, [
            'ownerRecord' => $apiUser ?? $this->apiUser,
            'pageClass' => ViewUser::class,
        ]);
    }

    // While the API is off administrators can still revoke service clients, but not create or change them.
    public function test_while_the_api_is_off_clients_can_be_revoked_but_not_created_or_changed(): void
    {
        [, $client] = resolve(CreateServiceClient::class)($this->apiUser, 'Sync', now()->addMonth());
        config(['api.enabled' => false]);

        Livewire::test(ServiceClientsRelationManager::class, ['ownerRecord' => $this->apiUser, 'pageClass' => ViewUser::class])
            ->assertActionHidden(TestAction::make('createServiceClient')->table())
            ->assertActionHidden(TestAction::make('rotateServiceClient')->table($client))
            ->assertActionHidden(TestAction::make('editServiceClientIpRestrictions')->table($client))
            ->assertActionVisible(TestAction::make('revokeServiceClient')->table($client));
    }
}
