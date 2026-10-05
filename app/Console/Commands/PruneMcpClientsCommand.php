<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Auth\Actions\Applications\RevokeOAuthApplication;
use App\Domains\Auth\Enums\ClientOrigin;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\Auth\Models\OAuthConnection;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Laravel\Passport\Passport;

/**
 * Cleans up the clients MCP clients register for themselves, which anyone can create:
 * deletes one nobody connected soon after it registered, and revokes one nobody has used for
 * a long time. Connections are kept apart from token rows, so `passport:purge` never makes a
 * client look unused. Administrator-registered and service clients are never touched.
 */
class PruneMcpClientsCommand extends Command
{
    protected $signature = 'mcp:prune-clients';

    protected $description = 'Delete self-registered MCP clients nobody connected, and revoke those nobody has used';

    public function handle(RevokeOAuthApplication $revoke): int
    {
        $deleted = 0;

        $unconnected = $this->dynamicClients()
            ->where('created_at', '<', Carbon::now()->subHours((int) config('mcp.cleanup.unconnected_hours')))
            ->whereDoesntHave('connections');

        foreach ($unconnected->lazyById() as $client) {
            Passport::authCode()->newQuery()->where('client_id', $client->getKey())->delete();
            $client->delete();
            $deleted++;
        }

        $revoked = 0;
        $cutoff = Carbon::now()->subDays((int) config('mcp.cleanup.unused_days'));

        $unused = $this->dynamicClients()
            ->where('revoked', false)
            ->where('created_at', '<', $cutoff)
            ->whereHas('connections')
            ->whereDoesntHave('connections', fn (Builder $query) => $query
                ->where(fn (Builder $recent) => $recent->where('last_used_at', '>=', $cutoff)->orWhere('connected_at', '>=', $cutoff))
                ->orWhereIn('oauth_connections.id', OAuthConnection::query()->live()->select('id')));

        foreach ($unused->lazyById() as $client) {
            $revoke($client);
            $revoked++;
        }

        $this->components->info("Deleted {$deleted} unconnected and revoked {$revoked} unused MCP client(s).");

        return self::SUCCESS;
    }

    /**
     * @return Builder<OAuthClient>
     */
    private function dynamicClients(): Builder
    {
        return OAuthClient::query()->where('origin', ClientOrigin::Dynamic);
    }
}
