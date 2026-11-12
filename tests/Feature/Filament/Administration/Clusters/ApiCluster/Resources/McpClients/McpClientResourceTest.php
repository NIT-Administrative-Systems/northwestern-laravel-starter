<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Administration\Clusters\ApiCluster\Resources\McpClients;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Api\Enums\CredentialStatus;
use App\Domains\User\Models\User;
use App\Filament\Administration\Clusters\ApiCluster;
use App\Filament\Administration\Clusters\ApiCluster\Resources\McpClients\McpClientResource;
use App\Filament\Administration\Clusters\ApiCluster\Resources\McpClients\Pages\ListMcpClients;
use App\Mcp\ClientMetadataDocuments;
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

    // While MCP is off, administrators can still see
    // and revoke clients, to shut it down cleanly.
    public function test_it_needs_manage_api_access_and_stays_open_while_mcp_is_off(): void
    {
        $this->assertTrue(McpClientResource::canAccess());

        config(['mcp.enabled' => false]);
        $this->assertTrue(McpClientResource::canAccess());

        $this->actingAs(User::factory()->create());
        $this->assertFalse(McpClientResource::canAccess());
    }

    // The API cluster holds MCP Clients, so it must show when only MCP is on.
    public function test_it_is_in_the_sidebar_when_only_mcp_is_on(): void
    {
        config(['api.enabled' => false]);

        // The panel's home sends someone without the overview to their first page.
        $this->followingRedirects()
            ->get('/administration')
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

    public function test_a_client_from_a_metadata_document_is_marked_with_its_host(): void
    {
        $this->fakeClientMetadataDocument();
        $client = resolve(ClientMetadataDocuments::class)->find(self::CLAUDE_CODE_METADATA_URL);
        $this->assertNotNull($client);

        Livewire::test(ListMcpClients::class)
            ->assertCanSeeTableRecords([$client])
            ->assertSee('Verified by claude.ai')
            ->assertDontSee('Name not verified');
    }

    public function test_an_administrator_revokes_a_client(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::UseMcp);
        $admin = auth()->user();
        $this->actingAs($user);
        $token = $this->issueMcpToken();
        $client = McpClientResource::getEloquentQuery()->sole();
        $this->assertNotNull($admin);
        $this->actingAs($admin);

        Livewire::test(ListMcpClients::class)
            ->callAction(TestAction::make('revoke')->table($client))
            ->assertNotified('MCP Client Revoked');

        $this->assertSame(CredentialStatus::Revoked, $client->fresh()?->status);
        $this->sendMcpMessage($token, 'tools/list')->assertUnauthorized();
    }
}
