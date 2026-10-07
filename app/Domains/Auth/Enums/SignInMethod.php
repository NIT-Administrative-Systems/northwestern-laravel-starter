<?php

declare(strict_types=1);

namespace App\Domains\Auth\Enums;

use App\Domains\Auth\SignIn;

/**
 * The ways a person can sign in. {@see SignIn::methods()} says which this application offers.
 */
enum SignInMethod: string
{
    /** Northwestern single sign-on, through the {@see SsoProvider} that's configured. */
    case Sso = 'sso';

    /** A code sent by email, for local accounts without a NetID. */
    case EmailCode = 'email-code';

    /** One click as a seeded demo user, in the `local` environment only. */
    case SignInAs = 'sign-in-as';

    /**
     * Whether the sign-in outlasts the session, through Laravel's remember cookie. Signing in again
     * with single sign-on is usually silent, since the identity provider still has a session, and
     * sign-in-as is one click. An email code costs a trip to the inbox, so it's remembered.
     */
    public function remembers(): bool
    {
        return $this === self::EmailCode;
    }
}
