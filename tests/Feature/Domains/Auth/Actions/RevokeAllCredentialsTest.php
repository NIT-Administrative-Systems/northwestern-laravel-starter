<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Actions;

use App\Domains\Auth\Actions\RevokeAllCredentials;
use App\Domains\Auth\Enums\CredentialStatus;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\IssuesPersonalAccessTokens;
use Tests\Concerns\IssuesServiceClientTokens;
use Tests\TestCase;

#[CoversClass(RevokeAllCredentials::class)]
final class RevokeAllCredentialsTest extends TestCase
{
    use IssuesPersonalAccessTokens, IssuesServiceClientTokens;

    public function test_it_revokes_a_persons_tokens(): void
    {
        $person = User::factory()->create();
        [, $token] = $this->personalAccessToken($person);

        resolve(RevokeAllCredentials::class)($person);

        $this->assertSame(CredentialStatus::Revoked, $token->fresh()?->status);
    }

    public function test_it_revokes_an_api_users_clients(): void
    {
        $apiUser = User::factory()->api()->create();
        [, $client] = $this->serviceClientToken($apiUser);

        resolve(RevokeAllCredentials::class)($apiUser);

        $this->assertSame(CredentialStatus::Revoked, $client->fresh()?->status);
    }

    public function test_deleting_a_user_revokes_everything(): void
    {
        $person = User::factory()->create();
        [, $token] = $this->personalAccessToken($person);
        $apiUser = User::factory()->api()->create();
        [, $client] = $this->serviceClientToken($apiUser);

        $person->delete();
        $apiUser->delete();

        $this->assertSame(CredentialStatus::Revoked, $token->fresh()?->status);
        $this->assertSame(CredentialStatus::Revoked, $client->fresh()?->status);
    }
}
