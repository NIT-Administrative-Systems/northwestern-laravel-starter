<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Api\Actions\PersonalAccessTokens;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Api\Actions\PersonalAccessTokens\CreatePersonalAccessToken;
use App\Domains\Api\CredentialAccess;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\TokenExpiration;
use App\Domains\Api\Models\OAuthClient;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use Laravel\Passport\PersonalAccessTokenFactory;
use Laravel\Passport\PersonalAccessTokenResult;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(CreatePersonalAccessToken::class)]
final class CreatePersonalAccessTokenTest extends TestCase
{
    public function test_it_creates_a_working_token_with_the_chosen_scopes_and_lifetime(): void
    {
        $user = $this->holder([SystemPermission::ViewUsers]);

        [$accessToken, $token] = $this->create($user, ['view-users'], TokenExpiration::OneMonth);

        $this->assertSame(['view-users'], $token->scopes);
        $this->assertSame('Export script', $token->name);
        $this->assertTrue($token->expires_at->isSameDay(now()->addDays(30)));

        $this->withToken($accessToken)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.username', $user->username);
    }

    // Passport signs every personal token for its one maximum lifetime; the chosen expiry is enforced on top.
    public function test_the_token_stops_working_when_its_chosen_lifetime_ends(): void
    {
        [$accessToken] = $this->create($this->holder(), [], TokenExpiration::OneMonth);

        $this->travel(31)->days();

        $this->withToken($accessToken)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_the_personal_access_client_is_created_once_when_first_needed(): void
    {
        $this->create($this->holder(), [], TokenExpiration::OneMonth);
        $this->create($this->holder(), [], TokenExpiration::OneMonth);

        $this->assertSame(1, OAuthClient::query()->where('grant_types', 'like', '%"personal_access"%')->count());
    }

    public function test_only_scopes_the_persons_permissions_cover_are_offered_and_accepted(): void
    {
        $user = $this->holder();

        $this->assertSame([], resolve(CredentialAccess::class)->grantableScopes($user, CredentialKind::PersonalAccessToken));

        $this->expectException(InvalidArgumentException::class);
        $this->create($user, ['view-users'], TokenExpiration::OneMonth);
    }

    public function test_it_requires_the_permission(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->create(User::factory()->create(), [], TokenExpiration::OneMonth);
    }

    public function test_api_users_cannot_create_personal_tokens(): void
    {
        $apiUser = User::factory()->api()->create();
        $apiUser->givePermissionTo(SystemPermission::CreatePersonalAccessTokens);

        $this->expectException(AuthorizationException::class);

        $this->create($apiUser, [], TokenExpiration::OneMonth);
    }

    public function test_it_is_refused_while_impersonating(): void
    {
        $impersonate = Mockery::mock();
        $impersonate->shouldReceive('isImpersonating')->andReturn(true);
        $impersonate->shouldReceive('getImpersonatorId')->andReturn(null);
        $this->app->instance('impersonate', $impersonate);

        $this->expectException(AuthorizationException::class);

        $this->create($this->holder(), [], TokenExpiration::OneMonth);
    }

    public function test_only_the_offered_lifetimes_up_to_the_maximum_are_accepted(): void
    {
        config(['api.personal_access_tokens.max_lifetime_days' => 90]);
        $user = $this->holder();

        $this->assertSame([TokenExpiration::OneMonth, TokenExpiration::ThreeMonths], TokenExpiration::forPersonalAccessTokens(90));

        foreach ([TokenExpiration::OneDay, TokenExpiration::SixMonths] as $lifetime) {
            try {
                $this->create($user, [], $lifetime);
                $this->fail("A lifetime of {$lifetime->getLabel()} was accepted.");
            } catch (InvalidArgumentException) {
                $this->assertSame(0, $user->tokens()->count());
            }
        }
    }

    public function test_a_person_can_hold_only_so_many_active_tokens(): void
    {
        config(['api.personal_access_tokens.max_active' => 2]);
        $user = $this->holder();

        $this->create($user, [], TokenExpiration::OneMonth);
        [, $second] = $this->create($user, [], TokenExpiration::OneMonth);

        try {
            $this->create($user, [], TokenExpiration::OneMonth);
            $this->fail('A third token was created.');
        } catch (InvalidArgumentException) {
            $second->revoke();
            $this->create($user, [], TokenExpiration::OneMonth);
            $this->assertSame(3, $user->tokens()->count());
        }
    }

    public function test_it_fails_loudly_when_passport_returns_no_token(): void
    {
        $user = $this->holder();
        $this->mock(PersonalAccessTokenFactory::class)
            ->shouldReceive('make')
            ->andReturn(new PersonalAccessTokenResult(['access_token' => 'unused', 'access_token_id' => 'missing']));

        $this->expectExceptionObject(new RuntimeException('Passport did not return the token it created.'));

        $this->create($user, [], TokenExpiration::OneMonth);
    }

    /**
     * @param  list<SystemPermission>  $permissions
     */
    private function holder(array $permissions = []): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::CreatePersonalAccessTokens, ...$permissions);

        return $user;
    }

    /**
     * @param  list<string>  $scopes
     * @return array{0: non-empty-string, 1: \App\Domains\Api\Models\OAuthToken}
     */
    private function create(User $user, array $scopes, TokenExpiration $lifetime): array
    {
        return resolve(CreatePersonalAccessToken::class)($user, 'Export script', $scopes, $lifetime);
    }
}
