<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Console\Commands\RevokeIneligibleCredentialsCommand;
use App\Domains\Auth\Enums\CredentialStatus;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\OAuthConnection;
use App\Domains\Auth\Models\OAuthToken;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\IssuesPersonalAccessTokens;
use Tests\Concerns\IssuesServiceClientTokens;
use Tests\Concerns\RunsAuthorizationCodeFlow;
use Tests\TestCase;

#[CoversClass(RevokeIneligibleCredentialsCommand::class)]
final class RevokeIneligibleCredentialsCommandTest extends TestCase
{
    use IssuesPersonalAccessTokens, IssuesServiceClientTokens, RunsAuthorizationCodeFlow;

    public function test_it_revokes_personal_tokens_whose_owners_lost_the_permission(): void
    {
        $lost = User::factory()->create();
        [, $lostToken] = $this->personalAccessToken($lost);
        $lost->revokePermissionTo(SystemPermission::CreatePersonalAccessTokens);

        $kept = User::factory()->create();
        [, $keptToken] = $this->personalAccessToken($kept);

        $this->artisan(RevokeIneligibleCredentialsCommand::class)
            ->expectsOutputToContain('Revoked the credentials of 0 deactivated account(s), 1 personal access token(s)')
            ->assertSuccessful();

        $this->assertSame(CredentialStatus::Revoked, $lostToken->fresh()?->status);
        $this->assertSame(CredentialStatus::Active, $keptToken->fresh()?->status);
    }

    public function test_it_disconnects_the_mcp_clients_of_people_who_lost_the_use_mcp_permission(): void
    {
        config(['mcp.enabled' => true]);
        $lost = User::factory()->create();
        $lost->givePermissionTo(SystemPermission::UseMcp);
        $this->actingAs($lost);
        $this->mcpToken();
        $lost->revokePermissionTo(SystemPermission::UseMcp);

        $kept = User::factory()->create();
        $kept->givePermissionTo(SystemPermission::UseMcp);
        $this->actingAs($kept);
        $keptToken = $this->mcpToken();

        $this->artisan(RevokeIneligibleCredentialsCommand::class)
            ->expectsOutputToContain('1 MCP client connection(s)')
            ->assertSuccessful();

        $this->assertSame([$kept->id], OAuthConnection::query()->pluck('user_id')->all());
        $this->assertFalse(OAuthToken::query()->where('user_id', $lost->id)->where('revoked', false)->exists());
        $this->mcp($keptToken, 'tools/list')->assertOk();
    }

    public function test_it_revokes_everything_belonging_to_deactivated_accounts(): void
    {
        $inactive = User::factory()->create();
        [, $token] = $this->personalAccessToken($inactive);
        $inactive->forceFill(['netid_inactive' => true])->saveQuietly();

        $this->artisan(RevokeIneligibleCredentialsCommand::class)
            ->expectsOutputToContain('Revoked the credentials of 1 deactivated account(s)')
            ->assertSuccessful();

        $this->assertSame(CredentialStatus::Revoked, $token->fresh()?->status);
    }
}
