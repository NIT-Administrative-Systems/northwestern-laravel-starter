<?php

declare(strict_types=1);

namespace App\Domains\Auth\Enums;

use App\Domains\User\Actions\PersistUserWithUniqueUsername;
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
 *
 * **SSO_PROVISIONING** — A role was assigned during first-time SSO login when
 * the user is provisioned from directory data.
 * - Triggered by: User creation in {@see PersistUserWithUniqueUsername}.
 */
enum RoleModificationOriginEnum: string
{
    case UI_ACTION = 'ui-action';
    case REMOVED_BY_DELETION = 'removed-by-deletion';
    case NETID_STATUS_CHANGE = 'netid-status-change';
    case SSO_PROVISIONING = 'sso-provisioning';
}
