<?php

declare(strict_types=1);

namespace App\Domains\User\Data;

use App\Domains\Core\Data\Preferences;
use App\Domains\User\Models\User;

/**
 * A person's own settings, edited on their Preferences page.
 *
 * Timezone isn't one: it's a column, because every date display reads it.
 *
 * Add a preference as a promoted constructor property with
 * a default, then add its field to the Preferences page:
 *
 * ```php
 * public function __construct(
 *     public bool $emailWeeklySummary = true,
 * ) {}
 * ```
 *
 * Read it with `$user->preferences->emailWeeklySummary`.
 *
 * @see User::$casts
 */
final readonly class UserPreferences extends Preferences
{
    public function __construct(
        /** Email the person before their personal access tokens expire. */
        public bool $emailBeforeAccessTokensExpire = true,
        /** Email when an application connects to their account; the bell always shows it. */
        public bool $emailWhenApplicationConnects = true,
        /** Email the announcements people are notified of; the bell always shows them. */
        public bool $emailAnnouncements = true,
    ) {
    }
}
