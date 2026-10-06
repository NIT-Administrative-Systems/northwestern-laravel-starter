<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Repositories;

use App\Domains\Auth\Actions\Api\CreateServiceClient;
use App\Domains\Auth\Repositories\OAuthClientRepository;
use App\Domains\User\Models\User;
use Laravel\Passport\ClientRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(OAuthClientRepository::class)]
final class OAuthClientRepositoryTest extends TestCase
{
    public function test_passport_uses_it(): void
    {
        $this->assertInstanceOf(OAuthClientRepository::class, resolve(ClientRepository::class));
    }

    public function test_a_malformed_client_id_is_an_unknown_client(): void
    {
        $this->postJson('/oauth/token', ['grant_type' => 'client_credentials', 'client_id' => 'not-a-uuid', 'client_secret' => 'secret'])
            ->assertUnauthorized()
            ->assertJsonPath('error', 'invalid_client');
    }

    public function test_a_real_client_is_still_found(): void
    {
        [, $client] = resolve(CreateServiceClient::class)(User::factory()->api()->create(), 'Sync', now()->addMonth());

        $this->assertTrue(resolve(ClientRepository::class)->find($client->getKey())?->is($client));
    }
}
