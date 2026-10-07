<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Actions\Api;

use App\Domains\Auth\Actions\Api\CreateServiceClient;
use App\Domains\Auth\Actions\Api\RevokeServiceClient;
use App\Domains\Auth\Actions\Api\RotateServiceClient;
use App\Domains\Auth\Enums\CredentialStatus;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\User\Models\User;
use InvalidArgumentException;
use Laravel\Passport\ClientRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RotateServiceClient::class)]
final class RotateServiceClientTest extends TestCase
{
    // The old client keeps working, so the integration can switch without downtime.
    public function test_it_adds_a_replacement_and_leaves_the_old_client_active(): void
    {
        $apiUser = User::factory()->api()->create();
        $admin = $this->administrator();
        [, $previous] = resolve(CreateServiceClient::class)($apiUser, 'Sync', now()->addDays(10));

        [$secret, $replacement] = resolve(RotateServiceClient::class)($previous, $admin, 'Sync 2026', now()->addDays(90), ['10.0.0.1']);

        $this->assertNotSame($previous->getKey(), $replacement->getKey());
        $this->assertNotSame('', $secret);
        $this->assertTrue($replacement->owner->is($apiUser));
        $this->assertSame($previous->getKey(), $replacement->rotated_from_client_id);
        $this->assertSame($admin->getKey(), $replacement->rotated_by_user_id);
        $this->assertSame(['10.0.0.1'], $replacement->allowed_ips);
        $this->assertSame(CredentialStatus::Active, $previous->fresh()?->status);
    }

    public function test_only_a_client_owned_by_an_api_user_can_be_rotated(): void
    {
        /** @var OAuthClient $client */
        $client = resolve(ClientRepository::class)->createClientCredentialsGrantClient('Ownerless');

        $this->expectException(InvalidArgumentException::class);

        resolve(RotateServiceClient::class)($client, $this->administrator(), 'Replacement', now()->addDays(30));
    }

    public function test_a_revoked_client_cannot_be_rotated(): void
    {
        [, $previous] = resolve(CreateServiceClient::class)(User::factory()->api()->create(), 'Sync', now()->addDays(10));
        resolve(RevokeServiceClient::class)($previous);

        $this->expectException(InvalidArgumentException::class);

        resolve(RotateServiceClient::class)($previous, $this->administrator(), 'Replacement', now()->addDays(30));
    }

    private function administrator(): User
    {
        $administrator = User::factory()->affiliate()->create();
        $administrator->givePermissionTo(SystemPermission::ManageApiAccess);

        return $administrator;
    }
}
