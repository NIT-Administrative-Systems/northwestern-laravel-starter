<?php

declare(strict_types=1);

namespace App\Domains\Api\Enums;

/**
 * Why {@see \App\Domains\Api\CredentialAccess} refused.
 */
enum AccessRefusal
{
    /** The operation doesn't exist for this kind and actor, such as a person modifying their own token. */
    case Unsupported;

    /** The feature the credential belongs to is turned off. */
    case FeatureOff;

    /** The actor is impersonating someone, and credentials never change under someone else's name. */
    case Impersonating;

    /** The actor, or the credential's holder, lacks the permission it needs. */
    case MissingPermission;

    /** The holder's account is deleted or deactivated. */
    case AccountInactive;

    /** The holder is the wrong kind of account: a person holding a service client, or an API user holding a token. */
    case WrongAccountType;

    /**
     * Whether the refusal lasts, so a credential refused for it should be revoked. A feature
     * that is turned off may be turned back on, so its credentials are kept.
     */
    public function isLasting(): bool
    {
        return in_array($this, [self::MissingPermission, self::AccountInactive, self::WrongAccountType], true);
    }
}
