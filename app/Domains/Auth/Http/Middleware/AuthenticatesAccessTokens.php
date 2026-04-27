<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Middleware;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\Auth\Models\AccessToken;
use Northwestern\SysDev\Chassis\Contracts\AccessTokenContract;
use Northwestern\SysDev\Chassis\Exceptions\MissingRequestIpForRestrictedTokenException;
use Northwestern\SysDev\Chassis\Http\Middleware\AuthenticatesAccessTokens as BaseAuthenticatesAccessTokens;

class AuthenticatesAccessTokens extends BaseAuthenticatesAccessTokens
{
    protected function findActiveToken(string $tokenHash): ?AccessTokenContract
    {
        return AccessToken::query()
            ->withWhereHas('user', fn ($query) => $query->where('auth_type', AuthType::API))
            ->where('token_hash', $tokenHash)
            ->active()
            ->first();
    }

    protected function hashToken(#[\SensitiveParameter] string $plainToken): string
    {
        return AccessToken::hashFromPlain($plainToken);
    }

    protected function reportMissingIp(array $allowedIps): void
    {
        report(new MissingRequestIpForRestrictedTokenException(array_values($allowedIps)));
    }
}
