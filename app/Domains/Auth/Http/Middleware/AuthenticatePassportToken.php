<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Middleware;

use App\Domains\Api\CredentialAccess;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\Auth\Models\OAuthConnection;
use App\Domains\Auth\Models\OAuthToken;
use App\Domains\User\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Laravel\Passport\Client;
use Laravel\Passport\Guards\TokenGuard;
use Northwestern\SysDev\Chassis\Enums\OAuthGrantType;
use Northwestern\SysDev\Chassis\Exceptions\MissingRequestIpForRestrictedTokenException;
use Northwestern\SysDev\Chassis\Http\Middleware\AuthenticatesPassportTokens;
use Northwestern\SysDev\Chassis\ValueObjects\ApiRequestContext;

/**
 * Authenticates the API's Passport access tokens. A service client's token acts as the
 * API user that owns the client; a client without one is refused.
 */
class AuthenticatePassportToken extends AuthenticatesPassportTokens
{
    /** Record a client's, a token's and a connection's last use at most this often, so busy integrations don't write on every request. */
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

    /**
     * Whether the token's holder may still use it, as {@see CredentialAccess} decides: a deleted
     * or deactivated account, or a holder who lost the permission the credential needs, is
     * refused at once, before the hourly sweep revokes it.
     */
    protected function isEligible(Authenticatable $user): bool
    {
        $client = OAuthClient::query()->find(Context::get(ApiRequestContext::OAUTH_CLIENT_ID));

        return $user instanceof User
            && $client instanceof OAuthClient
            && resolve(CredentialAccess::class)->decide($user, CredentialOperation::Use, CredentialKind::of($client), $user)->allowed;
    }

    protected function authenticated(Request $request, Client $client, ?Authenticatable $user): void
    {
        if (Cache::add("oauth-client-used:{$client->getKey()}", true, self::LAST_USED_RESOLUTION_SECONDS)) {
            $client->newQuery()->whereKey($client->getKey())->update(['last_used_at' => now()]);
        }

        $tokenId = Context::get(ApiRequestContext::OAUTH_TOKEN_ID);

        if (is_string($tokenId) && Cache::add("oauth-token-used:{$tokenId}", true, self::LAST_USED_RESOLUTION_SECONDS)) {
            OAuthToken::query()->whereKey($tokenId)->update(['last_used_at' => now()]);
        }

        if ($user instanceof User && Context::get(ApiRequestContext::OAUTH_GRANT_TYPE) === OAuthGrantType::AuthorizationCode->value
            && Cache::add("oauth-connection-used:{$user->getKey()}:{$client->getKey()}", true, self::LAST_USED_RESOLUTION_SECONDS)) {
            OAuthConnection::query()->where('user_id', $user->getKey())->where('oauth_client_id', $client->getKey())->update(['last_used_at' => now()]);
        }
    }

    protected function reportMissingIp(array $allowedIps): void
    {
        report(new MissingRequestIpForRestrictedTokenException(array_values($allowedIps)));
    }
}
