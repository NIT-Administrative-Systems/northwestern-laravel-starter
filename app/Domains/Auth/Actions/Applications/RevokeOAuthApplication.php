<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Applications;

use App\Domains\Auth\Models\OAuthClient;
use App\Domains\Auth\Models\OAuthConnection;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

/**
 * Revokes an application for everyone: the client, every access and refresh token issued to
 * it, its unredeemed authorization codes, and everyone's connection to it.
 */
readonly class RevokeOAuthApplication
{
    public function __invoke(OAuthClient $client): void
    {
        DB::transaction(function () use ($client): void {
            $tokens = Passport::token()->newQuery()->where('client_id', $client->getKey());

            Passport::refreshToken()->newQuery()
                ->whereIn('access_token_id', (clone $tokens)->select('id'))
                ->update(['revoked' => true]);

            $tokens->update(['revoked' => true]);

            Passport::authCode()->newQuery()->where('client_id', $client->getKey())->update(['revoked' => true]);

            OAuthConnection::query()->where('oauth_client_id', $client->getKey())->delete();

            $client->forceFill(['revoked' => true])->save();
        });
    }
}
