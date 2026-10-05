<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Personal;

use App\Domains\Auth\Models\OAuthToken;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Revokes a personal access token. People revoke their own on the Account page; an
 * administrator revoking someone else's is recorded as an audit event on that person.
 */
readonly class RevokePersonalAccessToken
{
    /**
     * @throws AuthorizationException
     */
    public function __invoke(OAuthToken $token, User $revokedBy): void
    {
        if ($revokedBy->isImpersonated()) {
            throw new AuthorizationException('Personal access tokens cannot be revoked while impersonating.');
        }

        $token->revoke();

        $owner = User::query()->find($token->user_id);

        if ($owner instanceof User && $owner->isNot($revokedBy)) {
            $owner->auditEvent = 'personal_access_token_revoked';
            $owner->isCustomEvent = true;
            $owner->auditCustomOld = [];
            $owner->auditCustomNew = [
                'token_id' => $token->getKey(),
                'name' => $token->name,
                'scopes' => $token->scopes,
            ];

            Event::dispatch(new AuditCustom($owner));
        }
    }
}
