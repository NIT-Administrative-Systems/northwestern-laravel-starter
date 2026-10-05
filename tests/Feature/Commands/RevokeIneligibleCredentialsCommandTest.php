<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Console\Commands\RevokeIneligibleCredentialsCommand;
use App\Domains\Auth\Enums\CredentialStatus;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\IssuesPersonalAccessTokens;
use Tests\Concerns\IssuesServiceClientTokens;
use Tests\TestCase;

#[CoversClass(RevokeIneligibleCredentialsCommand::class)]
final class RevokeIneligibleCredentialsCommandTest extends TestCase
{
    use IssuesPersonalAccessTokens, IssuesServiceClientTokens;

    public function test_it_revokes_personal_tokens_whose_owners_lost_the_permission(): void
    {
        $lost = User::factory()->create();
        [, $lostToken] = $this->personalAccessToken($lost);
        $lost->revokePermissionTo(SystemPermission::CreatePersonalAccessTokens);

        $kept = User::factory()->create();
        [, $keptToken] = $this->personalAccessToken($kept);

        $this->artisan(RevokeIneligibleCredentialsCommand::class)
            ->expectsOutputToContain('Revoked the credentials of 0 deactivated account(s) and 1 personal access token(s)')
            ->assertSuccessful();

        $this->assertSame(CredentialStatus::Revoked, $lostToken->fresh()?->status);
        $this->assertSame(CredentialStatus::Active, $keptToken->fresh()?->status);
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
