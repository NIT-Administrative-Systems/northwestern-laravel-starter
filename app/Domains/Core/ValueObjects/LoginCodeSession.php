<?php

declare(strict_types=1);

namespace App\Domains\Core\ValueObjects;

use App\Http\Controllers\Auth\Local\ResendLoginCodeController;
use App\Http\Controllers\Auth\Local\SendLoginCodeController;
use App\Http\Controllers\Auth\Local\ShowLoginCodeFormController;
use App\Http\Controllers\Auth\Local\VerifyLoginCodeController;

/**
 * Centralizes the session keys used by the local login code flow.
 *
 * All controllers in the login code sequence read/write the same set of keys
 * so that the code form, verification, and resends stay in sync with the
 * challenge that was issued.
 *
 * - @see SendLoginCodeController::class
 * - @see ShowLoginCodeFormController::class
 * - @see VerifyLoginCodeController::class
 * - @see ResendLoginCodeController::class
 */
final class LoginCodeSession
{
    public const string PREFIX = 'login_code.';

    public const string EMAIL = self::PREFIX . 'email';

    public const string CHALLENGE_ID = self::PREFIX . 'challenge_id';

    public const string RESEND_AVAILABLE_AT = self::PREFIX . 'resend_available_at';

    /** @var array<int, string> */
    public const array KEYS = [
        self::EMAIL,
        self::CHALLENGE_ID,
        self::RESEND_AVAILABLE_AT,
    ];
}
