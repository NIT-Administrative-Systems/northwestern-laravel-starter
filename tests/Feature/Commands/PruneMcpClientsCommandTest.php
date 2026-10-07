<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Console\Commands\PruneMcpClientsCommand;
use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Auth\Enums\CredentialStatus;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\Auth\Models\OAuthConnection;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\RunsAuthorizationCodeFlow;
use Tests\TestCase;

#[CoversClass(PruneMcpClientsCommand::class)]
final class PruneMcpClientsCommandTest extends TestCase
{
    use RunsAuthorizationCodeFlow;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mcp.enabled' => true]);
    }

    public function test_it_deletes_clients_nobody_connected_within_a_day(): void
    {
        $stale = $this->registerMcpClient('Stale');
        $this->travel(25)->hours();
        $fresh = $this->registerMcpClient('Fresh');

        $this->artisan(PruneMcpClientsCommand::class)
            ->expectsOutputToContain('Deleted 1 unconnected and revoked 0 unused MCP client(s).')
            ->assertSuccessful();

        $this->assertNull(OAuthClient::query()->find($stale->getKey()));
        $this->assertNotNull(OAuthClient::query()->find($fresh->getKey()));
    }

    // The refresh token lasts 30 days, so a connection unused for 90 is long dead.
    public function test_it_revokes_clients_nobody_has_used_for_ninety_days(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::UseMcp);
        $this->actingAs($user);
        $this->mcpToken();
        $unused = OAuthConnection::query()->sole()->oauth_client;

        $this->travel(60)->days();
        $this->mcpToken();
        $recent = OAuthConnection::query()->latest('id')->first()?->oauth_client;

        $this->travel(31)->days();

        $this->artisan(PruneMcpClientsCommand::class)
            ->expectsOutputToContain('Deleted 0 unconnected and revoked 1 unused MCP client(s).')
            ->assertSuccessful();

        $this->assertSame(CredentialStatus::Revoked, $unused?->fresh()?->status);
        $this->assertSame(CredentialStatus::Active, $recent?->fresh()?->status);
    }

    public function test_administrator_registered_applications_are_never_pruned(): void
    {
        $application = $this->registerApplication();
        $this->travel(200)->days();

        $this->artisan(PruneMcpClientsCommand::class)->assertSuccessful();

        $this->assertSame(CredentialStatus::Active, $application->fresh()?->status);
    }
}
