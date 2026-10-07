<?php

declare(strict_types=1);

namespace App\Domains\Api\Listeners;

use App\Domains\Api\Models\OAuthClient;
use App\Domains\Api\Models\OAuthConnection;
use App\Domains\Api\Models\OAuthToken;
use App\Domains\Api\Notifications\ApplicationConnectedNotification;
use App\Domains\User\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Passport\Events\AccessTokenCreated;

/**
 * Records a person's connection to an application when the application gets a token for
 * them through the authorization code flow, and alerts them the first time.
 *
 * Personal access tokens and service clients aren't connections and are ignored.
 */
class RecordOAuthConnection
{
    public function handle(AccessTokenCreated $event): void
    {
        if ($event->userId === null || $event->userId === '') {
            return;
        }

        $client = OAuthClient::query()->find($event->clientId);

        if (! $client instanceof OAuthClient || ! $client->hasGrantType('authorization_code')) {
            return;
        }

        $user = User::query()->find($event->userId);

        if (! $user instanceof User) {
            return;
        }

        $connection = OAuthConnection::query()->firstOrNew([
            'user_id' => $user->getKey(),
            'oauth_client_id' => $client->getKey(),
        ]);

        $isNew = ! $connection->exists;

        $connection->forceFill([
            'scopes' => OAuthToken::query()->whereKey($event->tokenId)->value('scopes') ?? [],
            'connected_at' => $connection->connected_at ?? Carbon::now(),
        ])->save();

        if ($isNew) {
            $user->notify(new ApplicationConnectedNotification($connection));
        }
    }
}
