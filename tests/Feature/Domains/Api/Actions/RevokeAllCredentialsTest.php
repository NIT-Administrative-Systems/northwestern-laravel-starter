<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Api\Actions;

use App\Domains\Api\Actions\RevokeAllCredentials;
use App\Domains\Api\Enums\CredentialStatus;
use App\Domains\Api\Models\OAuthConnection;
use App\Domains\Api\Models\OAuthToken;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\IssuesPersonalAccessTokens;
use Tests\Concerns\IssuesServiceClientTokens;
use Tests\Concerns\RunsAuthorizationCodeFlow;
use Tests\TestCase;

#[CoversClass(RevokeAllCredentials::class)]
final class RevokeAllCredentialsTest extends TestCase
{
    use IssuesPersonalAccessTokens, IssuesServiceClientTokens, RunsAuthorizationCodeFlow;

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

    // Deleting revokes credentials, which never change while someone is impersonating.
    public function test_deleting_a_user_while_impersonating_is_refused(): void
    {
        $person = User::factory()->create();
        [, $token] = $this->personalAccessToken($person);
        $this->impersonating(true);

        try {
            $person->delete();
            $this->fail('A user was deleted while impersonating.');
        } catch (AuthorizationException $e) {
            $this->assertSame("Users can't be deleted while impersonating someone. Stop impersonating, then try again.", $e->getMessage());
        }

        $this->assertFalse($person->fresh()?->trashed());
        $this->assertSame(CredentialStatus::Active, $token->fresh()?->status);
    }

    // The delete and the revocation happen together:
    // if revoking fails, the user isn't deleted.
    public function test_a_user_whose_credentials_cannot_be_revoked_is_not_deleted(): void
    {
        $person = User::factory()->create();
        [, $token] = $this->personalAccessToken($person);
        // Impersonation starts after the delete, so the revocation is refused.
        $this->impersonating(false, true);

        try {
            $person->delete();
            $this->fail('The credentials were revoked while impersonating.');
        } catch (AuthorizationException) {
            $this->assertFalse($person->fresh()?->trashed());
            $this->assertSame(CredentialStatus::Active, $token->fresh()?->status);
        }
    }

    // The action removes the connection too, so the person's list is empty.
    public function test_it_disconnects_a_persons_applications(): void
    {
        $person = User::factory()->create();
        $client = $this->registerApplication(scopes: []);
        $this->actingAs($person);
        [, $verifier] = $this->requestAuthorization($client, []);
        $this->exchange($client, $this->approve($client), $verifier)->assertOk();

        resolve(RevokeAllCredentials::class)($person);

        $this->assertSame(0, OAuthConnection::query()->count());
        $this->assertFalse(OAuthToken::query()->where('user_id', $person->getKey())->where('revoked', false)->exists());
    }

    private function impersonating(bool ...$answers): void
    {
        $impersonate = Mockery::mock();
        $impersonate->shouldReceive('isImpersonating')->andReturn(...$answers);
        $impersonate->shouldReceive('getImpersonatorId')->andReturn(null);
        $this->app->instance('impersonate', $impersonate);
    }
}
