<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Models;

use App\Domains\Auth\Actions\Api\CreateServiceClient;
use App\Domains\Auth\Enums\CredentialStatus;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\User\Models\User;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(OAuthClient::class)]
final class OAuthClientTest extends TestCase
{
    public function test_passport_uses_it(): void
    {
        $this->assertSame(OAuthClient::class, Passport::clientModel());
    }

    public function test_status_follows_revocation_and_secret_expiry(): void
    {
        $client = $this->client();
        $this->assertSame(CredentialStatus::Active, $client->status);
        $this->assertFalse($client->revoked);

        $this->travel(31)->days();
        $this->assertSame(CredentialStatus::Expired, $client->status);
        $this->assertTrue($client->revoked);

        $client->forceFill(['revoked' => true])->save();
        $this->assertSame(CredentialStatus::Revoked, $client->fresh()?->status);
    }

    // Passport treats a revoked client as gone, so an expired secret stops the client everywhere.
    public function test_passport_refuses_a_client_whose_secret_has_expired(): void
    {
        $client = $this->client();
        $clients = resolve(ClientRepository::class);

        $this->assertInstanceOf(\Laravel\Passport\Client::class, $clients->findActive($client->getKey()));

        $this->travel(31)->days();

        $this->assertNull($clients->findActive($client->getKey()));
    }

    public function test_the_active_scope_excludes_revoked_and_expired_clients(): void
    {
        $active = $this->client();
        $this->client()->forceFill(['revoked' => true])->save();
        $this->client()->forceFill(['secret_expires_at' => now()->subMinute()])->save();

        $this->assertSame([$active->getKey()], OAuthClient::query()->active()->pluck('id')->all());
    }

    private function client(): OAuthClient
    {
        [, $client] = resolve(CreateServiceClient::class)(User::factory()->api()->create(), 'Sync', now()->addDays(30));

        return $client;
    }
}
