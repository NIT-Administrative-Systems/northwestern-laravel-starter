<?php

declare(strict_types=1);

namespace App\Domains\Auth\ValueObjects;

use App\Domains\Auth\Http\Controllers\Local\ResendLoginCodeController;
use App\Domains\Auth\Http\Controllers\Local\SendLoginCodeController;
use App\Domains\Auth\Http\Controllers\Local\ShowLoginCodeFormController;
use App\Domains\Auth\Http\Controllers\Local\VerifyLoginCodeController;

/**
 * Centralizes the session keys used by the local login code flow.
 *
 * All controllers in the login code sequence read/write the same set of keys
 * so that the code form, verification, and resends stay in sync with the
 * challenge that was issued. The challenge ID is encrypted before being
 * stored so that placeholder values and real IDs look identical.
 *
 * - @see SendLoginCodeController
 * - @see ShowLoginCodeFormController
 * - @see VerifyLoginCodeController
 * - @see ResendLoginCodeController
 */
final class LoginCodeSession
{
    public const string PREFIX = 'login_code.';

    public const string EMAIL = self::PREFIX . 'email';

    public const string CHALLENGE_ID = self::PREFIX . 'challenge_id';

    /**
     * Stored encrypted, so the stored value is indistinguishable from a real
     * challenge ID for locally known users.
     */
    public const string RESEND_AVAILABLE_AT = self::PREFIX . 'resend_available_at';

    /** @var array<int, string> */
    public const array KEYS = [
        self::EMAIL,
        self::CHALLENGE_ID,
        self::RESEND_AVAILABLE_AT,
    ];
}
