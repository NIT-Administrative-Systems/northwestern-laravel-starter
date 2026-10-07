<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources\Users\RelationManagers;

use App\Domains\Auth\Actions\Api\CreateServiceClient;
use App\Domains\Auth\Enums\CredentialStatus;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Enums\TokenExpiration;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\User\Models\User;
use App\Filament\Resources\ServiceClients\Schemas\ServiceClientSchemas;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\ServiceClientsRelationManager;
use App\Providers\Filament\AdministrationPanelProvider;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
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

    // The secret is shown once, kept only encrypted in the session until the operator confirms.
    public function test_creating_a_client_shows_its_secret_once(): void
    {
        $component = $this->relationManager()
            ->mountTableAction('createServiceClient')
            ->fillForm(['name' => 'Production sync', 'expiration' => TokenExpiration::ThreeMonths->value, 'allowed_ips' => ['10.0.0.0/8']])
            ->goToNextWizardStep();

        /** @var OAuthClient $client */
        $client = OAuthClient::query()->whereMorphedTo('owner', $this->apiUser)->sole();
        $stored = session(ServiceClientSchemas::SESSION_KEY_CREATE);

        $this->assertSame('Production sync', $client->name);
        $this->assertSame(['10.0.0.0/8'], $client->allowed_ips);
        $this->assertSame($client->getKey(), $stored['client_id']);
        $this->assertTrue(Hash::check(Crypt::decryptString($stored['secret']), (string) $client->secret));

        $component->callMountedTableAction();

        $this->assertNull(session(ServiceClientSchemas::SESSION_KEY_CREATE));
    }

    // Only the final submit clears the secret, so an abandoned run must not leak into the next one.
    public function test_an_abandoned_create_does_not_carry_over_to_another_api_user(): void
    {
        $this->relationManager()
            ->mountTableAction('createServiceClient')
            ->fillForm(['name' => 'Abandoned', 'expiration' => TokenExpiration::ThreeMonths->value])
            ->goToNextWizardStep();

        $otherApiUser = User::factory()->api()->create();
        $this->relationManager($otherApiUser)
            ->mountTableAction('createServiceClient')
            ->fillForm(['name' => 'Second', 'expiration' => TokenExpiration::ThreeMonths->value])
            ->goToNextWizardStep();

        $client = OAuthClient::query()->whereMorphedTo('owner', $otherApiUser)->sole();
        $this->assertSame('Second', $client->name);
        $this->assertSame($client->getKey(), session(ServiceClientSchemas::SESSION_KEY_CREATE)['client_id']);
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
