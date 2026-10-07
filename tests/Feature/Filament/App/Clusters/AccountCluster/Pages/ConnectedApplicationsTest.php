<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\App\Clusters\AccountCluster\Pages;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Api\Models\OAuthConnection;
use App\Domains\User\Models\User;
use App\Filament\App\Clusters\AccountCluster\Pages\ConnectedApplications;
use App\Providers\Filament\AppPanelProvider;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Concerns\RunsAuthorizationCodeFlow;
use Tests\TestCase;

#[CoversNothing]
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
        $this->get('/app/account/connected-applications')->assertOk()->assertSee('No Connected Applications');
    }

    // AI clients are connections too, so the page stays while only the MCP server is on.
    public function test_it_shows_while_the_api_or_the_mcp_server_is_enabled(): void
    {
        config(['api.enabled' => false, 'mcp.enabled' => true]);
        $this->assertTrue(ConnectedApplications::canAccess());

        config(['mcp.enabled' => false]);
        $this->assertFalse(ConnectedApplications::canAccess());
    }

    public function test_it_lists_the_persons_live_connections(): void
    {
        $mine = $this->connect('Reporting Tool');
        [$theirs] = $this->connectAs(User::factory()->create(), 'Calendar');

        Livewire::test(ConnectedApplications::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs])
            ->assertSee('Reporting Tool');

        $this->travel(31)->days();

        Livewire::test(ConnectedApplications::class)->assertCanNotSeeTableRecords([$mine]);
    }

    // An MCP client named itself, so the list says so.
    public function test_a_self_registered_mcp_client_is_labelled(): void
    {
        config(['mcp.enabled' => true]);
        $this->user->givePermissionTo(SystemPermission::UseMcp);
        $this->mcpToken();

        Livewire::test(ConnectedApplications::class)
            ->assertSee('Claude Code')
            ->assertSee('AI client · name not verified');
    }

    public function test_a_person_disconnects_an_application(): void
    {
        [$theirs] = $this->connectAs(User::factory()->create(), 'Reporting Tool');
        $connection = $this->connect('Reporting Tool');

        Livewire::test(ConnectedApplications::class)->callAction(TestAction::make('disconnect')->table($connection));

        $this->assertSame([$theirs->getKey()], OAuthConnection::query()->pluck('id')->all());
    }

    // Disconnect All is scoped to the person by its own query; nobody else's connections or tokens are touched.
    public function test_a_person_disconnects_every_application(): void
    {
        [$theirs, $theirToken] = $this->connectAs(User::factory()->create(), 'Reporting Tool');
        $this->connect('Reporting Tool');
        $this->connect('Calendar');

        Livewire::test(ConnectedApplications::class)->callAction(TestAction::make('disconnectAll')->table());

        $this->assertSame([$theirs->getKey()], OAuthConnection::query()->pluck('id')->all());
        $this->withToken($theirToken)->getJson('/api/v1/me')->assertOk();
    }

    public function test_an_impersonator_sees_connections_but_cannot_disconnect_them(): void
    {
        $connection = $this->connect('Reporting Tool');

        $impersonate = Mockery::mock();
        $impersonate->shouldReceive('isImpersonating')->andReturn(true);
        $impersonate->shouldReceive('getImpersonatorId')->andReturn(null);
        $this->app->instance('impersonate', $impersonate);

        Livewire::test(ConnectedApplications::class)
            ->assertSee('You\'re impersonating this person.', escape: false)
            ->assertCanSeeTableRecords([$connection])
            ->assertActionHidden(TestAction::make('disconnect')->table($connection))
            ->assertActionHidden(TestAction::make('disconnectAll')->table());
    }

    private function connect(string $name): OAuthConnection
    {
        return $this->connectAs($this->user, $name)[0];
    }

    /**
     * @return array{0: OAuthConnection, 1: string} The connection and its access token
     */
    private function connectAs(User $person, string $name): array
    {
        $this->actingAs($person);
        $client = $this->registerApplication();
        $client->forceFill(['name' => $name])->save();
        [, $verifier] = $this->requestAuthorization($client);
        $accessToken = $this->exchange($client, $this->approve($client), $verifier)->assertOk()->json('access_token');
        $this->actingAs($this->user);

        return [OAuthConnection::query()->where('oauth_client_id', $client->getKey())->sole(), $accessToken];
    }

    // A person sees only the kinds whose feature is on, and Disconnect All leaves the rest alone.
    public function test_mcp_clients_are_left_out_while_mcp_is_off(): void
    {
        config(['mcp.enabled' => true]);
        $application = $this->connect('Reporting Tool');
        $this->user->givePermissionTo(SystemPermission::UseMcp);
        $client = $this->registerMcpClient();
        [, $verifier] = $this->requestAuthorization($client, ['mcp:use']);
        $this->exchange($client, $this->approve($client), $verifier)->assertOk();
        $mcp = OAuthConnection::query()->where('oauth_client_id', $client->getKey())->sole();
        config(['mcp.enabled' => false]);

        Livewire::test(ConnectedApplications::class)
            ->assertCanSeeTableRecords([$application])
            ->assertCanNotSeeTableRecords([$mcp])
            ->callAction(TestAction::make('disconnectAll')->table());

        $this->assertSame([$mcp->getKey()], OAuthConnection::query()->pluck('id')->all());
    }
}
