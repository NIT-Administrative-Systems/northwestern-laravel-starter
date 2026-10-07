<?php

declare(strict_types=1);

namespace App\Domains\Api\Passport;

use App\Domains\Api\Models\OAuthClient;
use App\Domains\User\Models\User;
use Laravel\Passport\Bridge\ScopeRepository;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

/**
 * Passport's scope repository, narrowed so a person can't give an application more than they
 * hold: an authorization code, and the token exchanged for it, carry only the requested scopes
 * in {@see OAuthConsent::grantableScopes()}, which the consent screen lists. Bound in place of
 * Passport's in OAuthServiceProvider.
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

        $grantable = resolve(OAuthConsent::class)->grantableScopes($user, $client);

        return array_values(array_filter($scopes, fn (ScopeEntityInterface $scope): bool => in_array($scope->getIdentifier(), $grantable, true)));
    }
}
