<?php

declare(strict_types=1);

namespace App\Domains\User\Enums;

use App\Domains\User\Listeners\ProcessNetIdUpdate;

/**
 * Describes the origin or cause of a role assignment or removal.
 *
 * This enum provides audit context for understanding why a user's roles changed.
 *
 * ## Origins
 *
 * **UI_ACTION** — A role was assigned or removed by an administrator through
 * the Filament UI.
 * - Triggered by: Role management actions performed manually in the UI.
 *
 * **REMOVED_BY_DELETION** — A role was detached from all users because the
 * underlying role definition was deleted.
 * - Triggered by: Role deletion operations in Filament or system tooling.
 *
 * **NETID_STATUS_CHANGE** — A role was removed due to a NetID lifecycle event
 * such as deactivation, deprovisioning, or security hold.
 * - Triggered by: NetID update webhooks processed in {@see ProcessNetIdUpdate}.
 */
enum RoleModificationOriginEnum: string
{
    case UI_ACTION = 'ui-action';
    case REMOVED_BY_DELETION = 'removed-by-deletion';
    case NETID_STATUS_CHANGE = 'netid-status-change';
}
