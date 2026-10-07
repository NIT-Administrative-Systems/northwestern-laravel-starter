<?php

declare(strict_types=1);

namespace App\Domains\Auth\Passport;

use App\Domains\Api\CredentialAccess;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\User\Models\User;
use Laravel\Passport\Bridge\ScopeRepository;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

/**
 * Passport's scope repository, narrowed so a person can't give an application more than they
 * hold: an authorization code, and the token exchanged for it, carry only the requested scopes
 * that {@see CredentialAccess::grantableScopes()} allows the person. The consent screen lists
 * the same scopes. Bound in place of Passport's in OAuthServiceProvider.
 */
class GrantableScopeRepository extends ScopeRepository
{
    /**
     * @param  ScopeEntityInterface[]  $scopes
     * @return ScopeEntityInterface[]
     */
    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null,
        ?string $authCodeId = null,
    ): array {
        $scopes = parent::finalizeScopes($scopes, $grantType, $clientEntity, $userIdentifier, $authCodeId);

        if ($grantType !== 'authorization_code' || $userIdentifier === null) {
            return $scopes;
        }

        $user = User::query()->find($userIdentifier);
        $client = OAuthClient::query()->find($clientEntity->getIdentifier());

        if (! $user instanceof User || ! $client instanceof OAuthClient) {
            return [];
        }

        $grantable = resolve(CredentialAccess::class)->grantableScopes($user, CredentialKind::of($client));

        return array_values(array_filter($scopes, fn (ScopeEntityInterface $scope): bool => array_key_exists($scope->getIdentifier(), $grantable)));
    }
}
