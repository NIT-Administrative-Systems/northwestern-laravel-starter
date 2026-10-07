<?php

declare(strict_types=1);

namespace App\Domains\Api\Concerns;

use App\Domains\Api\CredentialAccess;
use App\Domains\Api\Enums\AccessRefusal;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Api\ValueObjects\AccessDecision;
use App\Domains\User\Models\User;
use Illuminate\Auth\AuthenticationException;

/**
 * Asks {@see CredentialAccess} about the signed-in person, for pages, resources, relation
 * managers and actions that show or hide what someone may do with a credential:
 *
 * ```php
 * public static function canAccess(): bool
 * {
 *     return static::allowsCredential(CredentialOperation::See, CredentialKind::McpClient);
 * }
 *
 * Action::make('revoke')->visible(fn (OAuthToken $record) => $this->allowsCredential(CredentialOperation::Revoke, CredentialKind::PersonalAccessToken, $this->getOwnerRecord()));
 * ```
 *
 * Pass the holder as {@see CredentialAccess::decide()} takes it. With nobody signed in the
 * answer is no, never the system's.
 */
trait AuthorizesCredentials
{
    protected static function credentialAccess(CredentialOperation $operation, CredentialKind $kind, ?User $holder = null): AccessDecision
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return AccessDecision::refuse(AccessRefusal::MissingPermission, $kind);
        }

        return resolve(CredentialAccess::class)->decide($actor, $operation, $kind, $holder);
    }

    protected static function allowsCredential(CredentialOperation $operation, CredentialKind $kind, ?User $holder = null): bool
    {
        return static::credentialAccess($operation, $kind, $holder)->allowed;
    }

    /**
     * The signed-in person, to pass to an action as the person acting.
     *
     * @throws AuthenticationException
     */
    protected static function actingUser(): User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : throw new AuthenticationException();
    }
}
