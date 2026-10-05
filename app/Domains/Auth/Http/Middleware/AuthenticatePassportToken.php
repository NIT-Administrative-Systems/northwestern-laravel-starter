<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Middleware;

use App\Domains\Auth\Models\OAuthClient;
use App\Domains\User\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Client;
use Laravel\Passport\Guards\TokenGuard;
use Northwestern\SysDev\Chassis\Exceptions\MissingRequestIpForRestrictedTokenException;
use Northwestern\SysDev\Chassis\Http\Middleware\AuthenticatesPassportTokens;

/**
 * Authenticates the API's Passport access tokens. A service client's token acts as the
 * API user that owns the client; a client without one is refused.
 */
class AuthenticatePassportToken extends AuthenticatesPassportTokens
{
    /** Record a client's last use at most this often, so busy integrations don't write on every request. */
    private const int LAST_USED_RESOLUTION_SECONDS = 300;

    /**
     * Every API request acts for a user: a person, or the API user that owns a service client.
     */
    protected function allowsClientsWithoutUser(): bool
    {
        return false;
    }

    /**
     * Resolve the owning API user through the guard's user provider, which eager-loads roles
     * and skips deleted users, as it does for every other token.
     */
    protected function clientOwner(Client $client): ?Authenticatable
    {
        if ($client->getAttribute('owner_type') !== (new User())->getMorphClass()) {
            return null;
        }

        $guard = Auth::guard('api');

        return $guard instanceof TokenGuard ? $guard->getProvider()->retrieveById($client->getAttribute('owner_id')) : null;
    }

    protected function allowedIps(Client $client): ?array
    {
        return $client instanceof OAuthClient ? $client->allowed_ips : null;
    }

    protected function isEligible(Authenticatable $user): bool
    {
        return $user instanceof User && ! $user->trashed() && $user->netid_inactive !== true;
    }

    protected function authenticated(Request $request, Client $client, ?Authenticatable $user): void
    {
        if (Cache::add("oauth-client-used:{$client->getKey()}", true, self::LAST_USED_RESOLUTION_SECONDS)) {
            $client->newQuery()->whereKey($client->getKey())->update(['last_used_at' => now()]);
        }
    }

    protected function reportMissingIp(array $allowedIps): void
    {
        report(new MissingRequestIpForRestrictedTokenException(array_values($allowedIps)));
    }
}
