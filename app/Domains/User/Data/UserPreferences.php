<?php

declare(strict_types=1);

namespace App\Domains\User\Data;

use App\Domains\Core\Data\Preferences;
use App\Domains\User\Models\User;

/**
 * A user's own settings, stored in `users.preferences` and edited on the Account area's
 * Preferences page. Timezone is not here: it is a column, because every date display reads it.
 *
 * Add a preference as a promoted constructor property with a default, then add its field to
 * the Preferences page:
 *
 *     public function __construct(
 *         public bool $emailWeeklySummary = true,
 *     ) {}
 *
 * Read it with `$user->preferences->emailWeeklySummary`.
 *
 * @see User::$casts
 */
final readonly class UserPreferences extends Preferences
{
    public function __construct(
        /** Email me before my personal access tokens expire. */
        public bool $emailBeforeAccessTokensExpire = true,
        /** Email me when an application connects to my account. The in-app notification is always sent. */
        public bool $emailWhenApplicationConnects = true,
        /** Email me announcements an administrator chose to notify people about. The in-app notification is always sent. */
        public bool $emailAnnouncements = true,
    ) {
    }
}
