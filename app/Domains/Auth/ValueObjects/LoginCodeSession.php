<?php

declare(strict_types=1);

namespace App\Domains\Auth\ValueObjects;

use App\Domains\Auth\Models\LoginChallenge;
use App\Filament\App\Pages\Auth\EmailCodeLogin;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/**
 * Centralizes the session state of the local login code flow.
 *
 * The email step, the code step, and resends all read and write the same keys,
 * so the code form and verification stay in sync with the challenge that was
 * issued. The challenge ID is encrypted before being stored, and emails without
 * a local account store an encrypted decoy, so real IDs and decoys look identical.
 *
 * Every login code email links to the code step with the challenge ID encrypted in
 * the URL, so a code issued outside this browser (sent by an administrator, or read
 * on another device) can still be entered. The link alone signs no one in.
 *
 * - @see EmailCodeLogin
 */
final class LoginCodeSession
{
    public const string PREFIX = 'login_code.';

    public const string EMAIL = self::PREFIX . 'email';

    /**
     * Stored encrypted, so the stored value is indistinguishable from a real
     * challenge ID for locally known users.
     */
    public const string CHALLENGE_ID = self::PREFIX . 'challenge_id';

    /** The query parameter that carries the encrypted challenge ID in the email's link. */
    public const string LINK_PARAMETER = 'challenge';

    /** @var non-empty-list<non-empty-string> */
    public const array KEYS = [
        self::EMAIL,
        self::CHALLENGE_ID,
    ];

    /**
     * Store the email and the issued challenge, or a decoy when no challenge was issued.
     */
    public static function start(string $email, ?LoginChallenge $challenge): void
    {
        Session::put([
            self::EMAIL => $email,
            self::CHALLENGE_ID => self::encrypt($challenge),
        ]);
    }

    /**
     * Replace the stored challenge after a resend. A resend for an email without
     * a local account keeps the existing decoy.
     */
    public static function replaceChallenge(?LoginChallenge $challenge): void
    {
        if ($challenge instanceof LoginChallenge) {
            Session::put(self::CHALLENGE_ID, self::encrypt($challenge));
        }
    }

    /**
     * The code step's URL for a challenge, for the login code email.
     */
    public static function link(LoginChallenge $challenge): string
    {
        return route('filament.app.auth.login-code', [
            self::LINK_PARAMETER => self::encrypt($challenge),
        ]);
    }

    /**
     * Start the flow from an email's link. A token that cannot be decrypted, or a
     * challenge that is expired, used or locked, is ignored.
     */
    public static function startFromLink(string $token): bool
    {
        try {
            $challengeId = Crypt::decryptString($token);
        } catch (DecryptException) {
            return false;
        }

        $challenge = ctype_digit($challengeId) ? LoginChallenge::query()->find($challengeId) : null;

        if (! $challenge?->isActive()) {
            return false;
        }

        self::start($challenge->email, $challenge);

        return true;
    }

    public static function email(): ?string
    {
        $email = Session::get(self::EMAIL);

        return is_string($email) && $email !== '' ? $email : null;
    }

    /**
     * The decrypted challenge ID: numeric for a real challenge, a UUID for a decoy.
     * A value that cannot be decrypted is removed from the session.
     */
    public static function challengeId(): ?string
    {
        $encrypted = Session::get(self::CHALLENGE_ID);

        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            Session::forget(self::CHALLENGE_ID);

            return null;
        }
    }

    public static function forget(): void
    {
        Session::forget(self::KEYS);
    }

    private static function encrypt(?LoginChallenge $challenge): string
    {
        return Crypt::encryptString($challenge instanceof LoginChallenge
            ? (string) $challenge->id
            : Str::uuid()->toString());
    }
}
