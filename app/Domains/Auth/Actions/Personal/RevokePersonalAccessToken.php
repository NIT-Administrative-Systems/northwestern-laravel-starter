<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Personal;

use App\Domains\Api\CredentialAccess;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Auth\Models\OAuthToken;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Revokes a personal access token. People revoke their own on the Account page; an
 * administrator revoking someone else's, or the system sweeping one its holder can no longer
 * use, is recorded as an audit event on that person.
 */
readonly class RevokePersonalAccessToken
{
    public function __construct(
        private CredentialAccess $credentials,
    ) {
    }

    /**
     * @param  User|null  $revokedBy  The holder or an administrator; null when the system revokes it
     *
     * @throws AuthorizationException
     */
    public function __invoke(OAuthToken $token, ?User $revokedBy): void
    {
        $owner = User::withTrashed()->find($token->user_id);

        $this->credentials->decide($revokedBy, CredentialOperation::Revoke, CredentialKind::PersonalAccessToken, $owner)->authorize();

        $token->revoke();

        if ($owner instanceof User && ! $owner->is($revokedBy)) {
            $owner->recordCustomAudit('personal_access_token_revoked', [
                'token_id' => $token->getKey(),
                'name' => $token->name,
                'scopes' => $token->scopes,
            ]);
        }
    }
}
