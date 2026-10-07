<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Administration\Clusters\ApiCluster\Resources\OAuthApplications;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Api\Enums\CredentialStatus;
use App\Domains\Api\Models\OAuthClient;
use App\Domains\User\Models\User;
use App\Filament\Administration\Clusters\ApiCluster\Resources\OAuthApplications\OAuthApplicationResource;
use App\Filament\Administration\Clusters\ApiCluster\Resources\OAuthApplications\Pages\ListOAuthApplications;
use App\Filament\Support\RevealOnceSecret;
use App\Providers\Filament\AdministrationPanelProvider;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Concerns\IssuesServiceClientTokens;
use Tests\Concerns\RunsAuthorizationCodeFlow;
use Tests\TestCase;

#[CoversNothing]
final class OAuthApplicationResourceTest extends TestCase
{
    use IssuesServiceClientTokens, RunsAuthorizationCodeFlow;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AdministrationPanelProvider::ID);

        $admin = User::factory()->create();
        $admin->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ManageApiAccess);
        $this->actingAs($admin);
    }

    public function test_it_needs_manage_api_access(): void
    {
        $this->assertTrue(OAuthApplicationResource::canAccess());

        $this->actingAs(User::factory()->create());
        $this->assertFalse(OAuthApplicationResource::canAccess());
    }

    public function test_it_lists_applications_and_not_service_clients(): void
    {
        $application = $this->registerApplication();
        [, $serviceClient] = $this->serviceClientToken(User::factory()->api()->create());

        Livewire::test(ListOAuthApplications::class)
            ->assertCanSeeTableRecords([$application])
            ->assertCanNotSeeTableRecords([$serviceClient]);
    }

    public function test_registering_a_confidential_application_shows_its_secret_once(): void
    {
        $component = Livewire::test(ListOAuthApplications::class)
            ->mountAction(TestAction::make('register'))
            ->fillForm([
                'name' => 'Student Portal',
                'redirect_uris' => ['https://portal.example.edu/callback'],
                'scopes' => ['view-users'],
                'confidential' => true,
                'first_party' => false,
            ])
            ->goToNextWizardStep()
            ->assertHasNoFormErrors();

        $client = OAuthClient::query()->where('name', 'Student Portal')->sole();
        $registration = RevealOnceSecret::for('application:register');
        $this->assertSame($client->getKey(), $registration->identifier());
        $this->assertTrue(Hash::check((string) $registration->secret(), (string) $client->secret));

        $component->callMountedAction();
        $this->assertFalse($registration->issued());
    }

    // An abandoned registration must not stand in for a regeneration.
    public function test_an_abandoned_registration_does_not_carry_over_to_a_regeneration(): void
    {
        Livewire::test(ListOAuthApplications::class)
            ->mountAction(TestAction::make('register'))
            ->fillForm(['name' => 'Abandoned', 'redirect_uris' => ['https://abandoned.example.edu/cb'], 'confidential' => true, 'first_party' => false])
            ->goToNextWizardStep();

        [, $application] = resolve(\App\Domains\Api\Actions\Applications\RegisterOAuthApplication::class)('Portal', ['https://portal.example.edu/cb'], true, []);
        $before = $application->secret;

        Livewire::test(ListOAuthApplications::class)
            ->mountAction(TestAction::make('regenerateSecret')->table($application))
            ->goToNextWizardStep();

        $this->assertNotSame($before, $application->fresh()?->secret);
        $this->assertSame($application->getKey(), RevealOnceSecret::for('application:regenerate')->identifier($application));
    }

    public function test_redirect_uris_must_be_https_or_loopback(): void
    {
        Livewire::test(ListOAuthApplications::class)
            ->mountAction(TestAction::make('register'))
            ->fillForm(['name' => 'Bad', 'redirect_uris' => ['http://portal.example.edu/callback'], 'confidential' => false, 'first_party' => false])
            ->goToNextWizardStep()
            ->assertHasFormErrors(['redirect_uris.0']);

        $this->assertSame(0, OAuthClient::query()->where('name', 'Bad')->count());
    }

    public function test_an_application_can_be_edited_and_revoked(): void
    {
        $application = $this->registerApplication();

        Livewire::test(ListOAuthApplications::class)
            ->callAction(TestAction::make('edit')->table($application), [
                'name' => 'Renamed',
                'redirect_uris' => ['https://renamed.example.edu/cb'],
                'scopes' => [],
                'first_party' => true,
            ])
            ->assertHasNoFormErrors()
            ->callAction(TestAction::make('revoke')->table($application));

        $application->refresh();
        $this->assertSame('Renamed', $application->name);
        $this->assertTrue($application->first_party);
        $this->assertSame(CredentialStatus::Revoked, $application->status);
    }

    public function test_a_confidential_applications_secret_can_be_regenerated(): void
    {
        [, $application] = resolve(\App\Domains\Api\Actions\Applications\RegisterOAuthApplication::class)('Portal', ['https://portal.example.edu/cb'], true, []);
        $before = $application->secret;

        Livewire::test(ListOAuthApplications::class)
            ->mountAction(TestAction::make('regenerateSecret')->table($application))
            ->goToNextWizardStep()
            ->callMountedAction();

        $this->assertNotSame($before, $application->fresh()?->secret);
        $this->assertFalse(RevealOnceSecret::for('application:regenerate')->issued());
    }

    // While the API is off administrators can still see and revoke applications, but not add or change them.
    public function test_while_the_api_is_off_applications_can_be_revoked_but_not_registered_or_edited(): void
    {
        [, $client] = resolve(\App\Domains\Api\Actions\Applications\RegisterOAuthApplication::class)('Portal', ['https://portal.example.edu/cb'], true, []);
        config(['api.enabled' => false]);

        $this->assertTrue(OAuthApplicationResource::canAccess());

        Livewire::test(ListOAuthApplications::class)
            ->assertActionHidden('register')
            ->assertActionHidden(TestAction::make('edit')->table($client))
            ->assertActionHidden(TestAction::make('regenerateSecret')->table($client))
            ->assertActionVisible(TestAction::make('revoke')->table($client));
    }
}
