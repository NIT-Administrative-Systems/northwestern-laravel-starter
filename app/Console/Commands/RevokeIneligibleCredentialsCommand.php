<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Api\CredentialAccess;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Auth\Actions\Api\RevokeServiceClient;
use App\Domains\Auth\Actions\Applications\DisconnectApplication;
use App\Domains\Auth\Actions\Personal\RevokePersonalAccessToken;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\Auth\Models\OAuthConnection;
use App\Domains\Auth\Models\OAuthToken;
use App\Domains\User\Models\User;
use Illuminate\Console\Command;

/**
 * Revokes credentials their holders can no longer use: each live personal access token,
 * connection and service client that {@see CredentialAccess} refuses for a lasting reason,
 * such as a deactivated or deleted account or a lost permission. A feature that is turned off
 * isn't one: its credentials wait for it to be turned back on.
 *
 * The API already refuses these on every request; this makes the records say so, so the
 * Account and Administration pages show them as revoked. Each goes through its own action, so
 * each is audited.
 */
class RevokeIneligibleCredentialsCommand extends Command
{
    protected $signature = 'oauth:revoke-ineligible';

    protected $description = 'Revoke the API credentials their holders can no longer use';

    public function handle(
        CredentialAccess $credentials,
        RevokePersonalAccessToken $revokePersonalAccessToken,
        DisconnectApplication $disconnectApplication,
        RevokeServiceClient $revokeServiceClient,
    ): int {
        $refused = fn (?User $holder, CredentialKind $kind): bool => $holder instanceof User
            && $credentials->decide($holder, CredentialOperation::Use, $kind, $holder)->reason?->isLasting() === true;

        $tokens = 0;

        foreach (OAuthToken::query()->personal()->active()->lazyById() as $token) {
            if ($refused(User::withTrashed()->find($token->user_id), CredentialKind::PersonalAccessToken)) {
                $revokePersonalAccessToken($token, null);
                $tokens++;
            }
        }

        $connections = 0;

        foreach (OAuthConnection::query()->with('oauth_client')->lazyById() as $connection) {
            $client = $connection->oauth_client;

            if ($client instanceof OAuthClient && $refused(User::withTrashed()->find($connection->user_id), CredentialKind::of($client))) {
                $disconnectApplication($connection, null);
                $connections++;
            }
        }

        $clients = 0;

        // Not the serviceClients() scope: it hides clients whose API user was deleted.
        $serviceClients = OAuthClient::query()->where('owner_type', (new User())->getMorphClass())->where('revoked', false);

        foreach ($serviceClients->lazyById() as $client) {
            if ($refused(User::withTrashed()->find($client->getAttribute('owner_id')), CredentialKind::ServiceClient)) {
                $revokeServiceClient($client, null);
                $clients++;
            }
        }

        $this->components->info("Revoked {$tokens} personal access token(s), {$connections} connection(s) and {$clients} service client(s) their holders can no longer use.");

        return self::SUCCESS;
    }
}
