<?php

declare(strict_types=1);

namespace App\Domains\Auth\Enums;

use App\Domains\Auth\SignIn;

/**
 * The ways to sign in. {@see SignIn::methods()} says which are offered.
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
     * Determine whether the sign-in outlasts the session, through the remember cookie.
     *
     * An email code costs a trip to the inbox, so it's remembered. Signing in
     * again with SSO is usually silent, and sign-in-as is a single click.
     */
    public function remembers(): bool
    {
        return $this === self::EmailCode;
    }
}
