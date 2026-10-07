<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Actions\Api;

use App\Domains\Auth\Actions\Api\CreateServiceClient;
use App\Domains\Auth\Enums\ClientOrigin;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Core\Models\Audit;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CreateServiceClient::class)]
final class CreateServiceClientTest extends TestCase
{
    public function test_it_creates_a_client_credentials_client_owned_by_the_api_user(): void
    {
        $apiUser = User::factory()->api()->create();

        [$secret, $client] = resolve(CreateServiceClient::class)($apiUser, 'Nightly sync', now()->addDays(90), ['10.0.0.0/8']);

        $this->assertTrue($client->owner->is($apiUser));
        $this->assertSame(['client_credentials'], $client->grant_types);
        $this->assertSame(ClientOrigin::Administrator, $client->origin);
        $this->assertSame(['10.0.0.0/8'], $client->allowed_ips);
        $this->assertTrue($client->secret_expires_at->isSameDay(now()->addDays(90)));
        $this->assertTrue(Hash::check($secret, $client->secret));
        $this->assertNull($client->rotated_from_client_id);
    }

    // The audit lives on the API user; it records the client, never its secret.
    public function test_it_is_audited_on_the_api_user_without_the_secret(): void
    {
        $apiUser = User::factory()->api()->create();

        [$secret, $client] = resolve(CreateServiceClient::class)($apiUser, 'Nightly sync', now()->addDays(90));

        $audit = Audit::query()->where('auditable_type', $apiUser->getMorphClass())->where('auditable_id', $apiUser->getKey())->where('event', 'service_client_created')->sole();
        $this->assertSame($client->getKey(), $audit->new_values['client_id']);
        $this->assertSame('Nightly sync', $audit->new_values['name']);
        $this->assertStringNotContainsString($secret, json_encode($audit->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public function test_an_empty_allowlist_means_no_restriction(): void
    {
        [, $client] = resolve(CreateServiceClient::class)(User::factory()->api()->create(), 'Sync', now()->addDays(30), []);

        $this->assertNull($client->allowed_ips);
    }

    public function test_only_api_users_own_service_clients(): void
    {
        $this->expectException(InvalidArgumentException::class);

        resolve(CreateServiceClient::class)(User::factory()->create(), 'Sync', now()->addDays(30));
    }

    public function test_a_secret_must_expire_within_a_year(): void
    {
        $apiUser = User::factory()->api()->create();

        foreach ([now()->subDay(), now()->addYears(2)] as $expiresAt) {
            try {
                resolve(CreateServiceClient::class)($apiUser, 'Sync', $expiresAt);
                $this->fail("A secret expiring {$expiresAt} was accepted.");
            } catch (InvalidArgumentException) {
                $this->assertSame(0, $apiUser->oauthApps()->count());
            }
        }
    }

    // A secret outlives the session, so an impersonator can't take one away.
    public function test_it_is_refused_while_impersonating(): void
    {
        $apiUser = User::factory()->api()->create();
        $administrator = $this->administrator();
        $impersonate = Mockery::mock();
        $impersonate->shouldReceive('isImpersonating')->andReturn(true);
        $impersonate->shouldReceive('getImpersonatorId')->andReturn(null);
        $this->app->instance('impersonate', $impersonate);

        try {
            resolve(CreateServiceClient::class)($apiUser, 'Sync', now()->addDays(30), createdBy: $administrator);
            $this->fail('A service client was created while impersonating.');
        } catch (AuthorizationException) {
            $this->assertSame(0, $apiUser->oauthApps()->count());
        }
    }

    private function administrator(): User
    {
        $administrator = User::factory()->affiliate()->create();
        $administrator->givePermissionTo(SystemPermission::ManageApiAccess);

        return $administrator;
    }
}
