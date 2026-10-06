<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Auth\Actions\Applications\DisconnectApplication;
use App\Domains\Auth\Actions\RevokeAllCredentials;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\Auth\Models\OAuthConnection;
use App\Domains\Auth\Models\OAuthToken;
use App\Domains\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Revokes credentials their holders may no longer have: everything belonging to deactivated
 * or deleted accounts, the personal access tokens of people who lost the permission to hold
 * them, and the MCP clients connected by people who lost the permission to use MCP.
 *
 * The API already refuses these on every request; this makes the records say so, so the
 * Account and Administration pages show them as revoked.
 */
class RevokeIneligibleCredentialsCommand extends Command
{
    protected $signature = 'oauth:revoke-ineligible';

    protected $description = 'Revoke the API credentials of deactivated accounts, and personal access tokens and MCP clients their owners may no longer hold';

    public function handle(RevokeAllCredentials $revokeAllCredentials, DisconnectApplication $disconnect): int
    {
        $deactivated = User::query()
            ->withTrashed()
            ->where(fn (Builder $query) => $query->whereNotNull('deleted_at')->orWhere('netid_inactive', true))
            ->where(fn (Builder $query) => $query
                ->whereExists(OAuthToken::query()->whereColumn('oauth_access_tokens.user_id', 'users.id')->where('revoked', false)->toBase())
                ->orWhereExists(OAuthClient::query()->whereColumn('oauth_clients.owner_id', 'users.id')->where('owner_type', (new User())->getMorphClass())->where('revoked', false)->toBase()));

        $accounts = 0;

        foreach ($deactivated->lazyById() as $user) {
            $revokeAllCredentials($user);
            $accounts++;
        }

        $tokens = 0;

        $holders = User::query()->whereIn('id', OAuthToken::query()->personal()->where('revoked', false)->select('user_id'));

        foreach ($holders->lazyById() as $user) {
            if (! $user->can(SystemPermission::CreatePersonalAccessTokens)) {
                $tokens += OAuthToken::query()->personal()->where('user_id', $user->getKey())->where('revoked', false)->update(['revoked' => true]);
            }
        }

        $mcpConnections = 0;

        $connections = OAuthConnection::query()
            ->with(['user', 'oauth_client'])
            ->whereHas('oauth_client', fn (Builder $query) => $query->mcpClients());

        foreach ($connections->lazyById() as $connection) {
            if ($connection->user instanceof User && ! $connection->user->can(SystemPermission::UseMcp)) {
                $disconnect($connection, $connection->user);
                $mcpConnections++;
            }
        }

        $this->components->info("Revoked the credentials of {$accounts} deactivated account(s), {$tokens} personal access token(s) and {$mcpConnections} MCP client connection(s) whose owners lost the permission.");

        return self::SUCCESS;
    }
}
