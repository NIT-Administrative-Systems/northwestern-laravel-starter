<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Middleware;

use App\Domains\Auth\Enums\RoleTypeEnum;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Http\Middleware\AuthenticatePassportToken;
use App\Domains\Auth\Models\OAuthConnection;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Once;
use Laravel\Passport\ClientRepository;
use Northwestern\SysDev\Chassis\ValueObjects\ApiRequestContext;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\IssuesPersonalAccessTokens;
use Tests\Concerns\IssuesServiceClientTokens;
use Tests\Concerns\RunsAuthorizationCodeFlow;
use Tests\TestCase;

#[CoversClass(AuthenticatePassportToken::class)]
final class AuthenticatePassportTokenTest extends TestCase
{
    use IssuesPersonalAccessTokens, IssuesServiceClientTokens, RunsAuthorizationCodeFlow;

    public function test_a_service_client_acts_as_its_api_user(): void
    {
        $apiUser = User::factory()->api()->create();
        [$token, $client] = $this->serviceClientToken($apiUser);

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.username', $apiUser->username);

        $this->assertSame('client', Context::get(ApiRequestContext::PRINCIPAL_TYPE));
        $this->assertSame($client->getKey(), Context::get(ApiRequestContext::OAUTH_CLIENT_ID));
    }

    // Passport's guard is the default during API requests, but roles and permissions belong to `web`.
    public function test_permission_checks_work_during_api_requests(): void
    {
        Route::middleware(['api', AuthenticatePassportToken::class])
            ->get('/api/test/permission', fn (Request $request) => response()->json(['can_view_users' => $request->user()?->can(SystemPermission::ViewUsers)]));

        $apiUser = User::factory()->api()->create();
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApiIntegration)->create();
        $role->givePermissionTo(SystemPermission::ViewUsers);
        $apiUser->roles()->attach($role);
        [$token] = $this->serviceClientToken($apiUser);

        $this->withToken($token)->getJson('/api/test/permission')->assertOk()->assertJsonPath('can_view_users', true);
    }

    public function test_the_clients_ip_allowlist_applies(): void
    {
        [$token, $client] = $this->serviceClientToken(User::factory()->api()->create());
        $client->forceFill(['allowed_ips' => ['10.0.0.0/8']])->save();
        Once::flush(); // Passport memoizes client lookups per request; these test requests share one.

        $this->withToken($token)->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->getJson('/api/v1/me')->assertUnauthorized();
        $this->assertSame('ip-denied', Context::get(ApiRequestContext::FAILURE_REASON));

        $this->withToken($token)->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])->getJson('/api/v1/me')->assertOk();
    }

    public function test_a_client_whose_secret_has_expired_is_refused(): void
    {
        [$token, $client] = $this->serviceClientToken(User::factory()->api()->create());
        $client->forceFill(['secret_expires_at' => now()->subMinute()])->save();
        Once::flush(); // Passport memoizes client lookups per request; these test requests share one.

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_a_deleted_api_user_is_refused(): void
    {
        $apiUser = User::factory()->api()->create();
        [$token] = $this->serviceClientToken($apiUser);
        $apiUser->delete();

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
        $this->assertSame('token-invalid-or-expired', Context::get(ApiRequestContext::FAILURE_REASON));
    }

    public function test_a_person_with_an_inactive_netid_is_refused(): void
    {
        $user = User::factory()->create(['netid_inactive' => true]);
        resolve(ClientRepository::class)->createPersonalAccessGrantClient('Personal', 'users');
        $token = $user->createToken('Script')->accessToken;

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
        $this->assertSame('token-invalid-or-expired', Context::get(ApiRequestContext::FAILURE_REASON));
    }

    // Every API request acts for a user; a client with no API user behind it has no permissions.
    public function test_a_client_without_an_owner_is_refused(): void
    {
        $client = resolve(ClientRepository::class)->createClientCredentialsGrantClient('Ownerless');
        $token = $this->postJson('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
        ])->assertOk()->json('access_token');

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_a_personal_token_stops_working_when_its_owner_loses_the_permission(): void
    {
        $user = User::factory()->create();
        [$token] = $this->personalAccessToken($user);
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();

        $user->revokePermissionTo(SystemPermission::CreatePersonalAccessTokens);

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
        $this->assertSame('token-invalid-or-expired', Context::get(ApiRequestContext::FAILURE_REASON));
    }

    public function test_it_records_when_a_personal_token_was_last_used(): void
    {
        [$token, $record] = $this->personalAccessToken(User::factory()->create());

        $this->withToken($token)->getJson('/api/v1/me')->assertOk();

        $this->assertNotNull($record->fresh()?->last_used_at);
    }

    public function test_it_records_when_a_connected_application_was_last_used(): void
    {
        $this->actingAs(User::factory()->create());
        $client = $this->registerApplication();
        [, $verifier] = $this->requestAuthorization($client);
        $token = $this->exchange($client, $this->approve($client), $verifier)->json('access_token');

        $this->withToken($token)->getJson('/api/v1/me')->assertOk();

        $this->assertNotNull(OAuthConnection::query()->sole()->last_used_at);
    }

    public function test_it_records_when_a_client_was_last_used_at_most_every_five_minutes(): void
    {
        [$token, $client] = $this->serviceClientToken(User::factory()->api()->create());

        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
        $firstUse = $client->fresh()?->last_used_at;
        $this->assertNotNull($firstUse);

        $this->travel(2)->minutes();
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
        $this->assertTrue($firstUse->eq($client->fresh()?->last_used_at));

        $this->travel(4)->minutes();
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
        $this->assertTrue($client->fresh()?->last_used_at->gt($firstUse));
    }
}
