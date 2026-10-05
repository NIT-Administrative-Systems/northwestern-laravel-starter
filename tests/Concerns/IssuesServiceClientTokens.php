<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Domains\Auth\Actions\Api\CreateServiceClient;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\User\Models\User;
use Illuminate\Foundation\Testing\TestCase;

/**
 * Creates a service client for an API user and exchanges it at `/oauth/token`, as an
 * integration would.
 *
 * @mixin TestCase
 */
trait IssuesServiceClientTokens
{
    /**
     * @return array{0: string, 1: OAuthClient} The access token and the client
     */
    protected function serviceClientToken(User $apiUser): array
    {
        [$secret, $client] = resolve(CreateServiceClient::class)($apiUser, 'Test client', now()->addMonth());

        $accessToken = $this->postJson('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->getKey(),
            'client_secret' => $secret,
        ])->assertOk()->json('access_token');

        return [$accessToken, $client];
    }
}
