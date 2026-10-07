<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Clusters\ApiCluster\Resources\McpClients;

use App\Domains\Auth\Enums\CredentialStatus;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Models\User;
use App\Filament\Clusters\ApiCluster;
use App\Filament\Clusters\ApiCluster\Resources\McpClients\McpClientResource;
use App\Filament\Clusters\ApiCluster\Resources\McpClients\Pages\ListMcpClients;
use App\Providers\Filament\AdministrationPanelProvider;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Concerns\RunsAuthorizationCodeFlow;
use Tests\TestCase;

#[CoversNothing]
final class McpClientResourceTest extends TestCase
{
    use RunsAuthorizationCodeFlow;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mcp.enabled' => true]);
        Filament::setCurrentPanel(AdministrationPanelProvider::ID);

        $admin = User::factory()->create();
        $admin->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ManageApiAccess);
        $this->actingAs($admin);
    }

    // While MCP is off administrators can still see and revoke the clients, to shut it down cleanly.
    public function test_it_needs_manage_api_access_and_stays_open_while_mcp_is_off(): void
    {
        $this->assertTrue(McpClientResource::canAccess());

        config(['mcp.enabled' => false]);
        $this->assertTrue(McpClientResource::canAccess());

        $this->actingAs(User::factory()->create());
        $this->assertFalse(McpClientResource::canAccess());
    }

    // MCP Clients sits in the API cluster, which must still show in the sidebar when only MCP is on.
    public function test_it_is_in_the_sidebar_when_only_mcp_is_on(): void
    {
        config(['api.enabled' => false]);

        $this->get('/administration')
            ->assertOk()
            ->assertSee('href="' . ApiCluster::getUrl(panel: AdministrationPanelProvider::ID) . '"', escape: false);

        $this->get(McpClientResource::getUrl(panel: AdministrationPanelProvider::ID))->assertOk();
    }

    public function test_it_lists_only_self_registered_clients(): void
    {
        $mcpClient = $this->registerMcpClient('Claude Code');
        $application = $this->registerApplication();

        Livewire::test(ListMcpClients::class)
            ->assertCanSeeTableRecords([$mcpClient])
            ->assertCanNotSeeTableRecords([$application])
            ->assertSee('Name not verified');
    }

    public function test_an_administrator_revokes_a_client(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::UseMcp);
        $admin = auth()->user();
        $this->actingAs($user);
        $token = $this->mcpToken();
        $client = McpClientResource::getEloquentQuery()->sole();
        $this->assertNotNull($admin);
        $this->actingAs($admin);

        Livewire::test(ListMcpClients::class)
            ->callAction(TestAction::make('revoke')->table($client))
            ->assertNotified('MCP Client Revoked');

        $this->assertSame(CredentialStatus::Revoked, $client->fresh()?->status);
        $this->mcp($token, 'tools/list')->assertUnauthorized();
    }
}
