<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\App\Clusters\AccountCluster\Pages;

use App\Domains\Auth\Models\OAuthConnection;
use App\Domains\User\Models\User;
use App\Filament\App\Clusters\AccountCluster\Pages\ConnectedApplications;
use App\Providers\Filament\AppPanelProvider;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\RunsAuthorizationCodeFlow;
use Tests\TestCase;

#[CoversClass(ConnectedApplications::class)]
final class ConnectedApplicationsTest extends TestCase
{
    use RunsAuthorizationCodeFlow;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AppPanelProvider::ID);

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_every_person_can_open_it(): void
    {
        $this->get('/app/account/connected-applications')->assertOk()->assertSee('No connected applications');
    }

    public function test_it_lists_the_persons_live_connections(): void
    {
        $mine = $this->connect('Reporting Tool');
        $this->actingAs(User::factory()->create());
        $theirs = $this->connect('Calendar');
        $this->actingAs($this->user);

        Livewire::test(ConnectedApplications::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs])
            ->assertSee('Reporting Tool');

        $this->travel(31)->days();

        Livewire::test(ConnectedApplications::class)->assertCanNotSeeTableRecords([$mine]);
    }

    public function test_a_person_disconnects_an_application(): void
    {
        $connection = $this->connect('Reporting Tool');

        Livewire::test(ConnectedApplications::class)->callAction(TestAction::make('disconnect')->table($connection));

        $this->assertSame(0, OAuthConnection::query()->count());
    }

    public function test_a_person_disconnects_every_application(): void
    {
        $this->connect('Reporting Tool');
        $this->connect('Calendar');

        Livewire::test(ConnectedApplications::class)->callAction(TestAction::make('disconnectAll')->table());

        $this->assertSame(0, OAuthConnection::query()->count());
    }

    public function test_an_impersonator_sees_connections_but_cannot_disconnect_them(): void
    {
        $connection = $this->connect('Reporting Tool');

        $impersonate = Mockery::mock();
        $impersonate->shouldReceive('isImpersonating')->andReturn(true);
        $impersonate->shouldReceive('getImpersonatorId')->andReturn(null);
        $this->app->instance('impersonate', $impersonate);

        Livewire::test(ConnectedApplications::class)
            ->assertSee('You are impersonating this user.')
            ->assertCanSeeTableRecords([$connection])
            ->assertActionHidden(TestAction::make('disconnect')->table($connection))
            ->assertActionHidden(TestAction::make('disconnectAll')->table());
    }

    private function connect(string $name): OAuthConnection
    {
        $client = $this->registerApplication();
        $client->forceFill(['name' => $name])->save();
        [, $verifier] = $this->requestAuthorization($client);
        $this->exchange($client, $this->approve($client), $verifier)->assertOk();

        return OAuthConnection::query()->where('oauth_client_id', $client->getKey())->sole();
    }
}
