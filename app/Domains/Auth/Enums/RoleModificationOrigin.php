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
 * **UiAction** — A role was assigned or removed by an administrator through
 * the Filament UI.
 * - Triggered by: Role management actions performed manually in the UI.
 *
 * **RemovedByDeletion** — A role was detached from all users because the
 * underlying role definition was deleted.
 * - Triggered by: Role deletion operations in Filament or system tooling.
 *
 * **NetIdStatusChange** — A role was removed due to a NetID lifecycle event
 * such as deactivation, deprovisioning, or security hold.
 * - Triggered by: NetID update webhooks processed in {@see ProcessNetIdUpdate}.
 *
 * **SsoProvisioning** — A role was assigned during first-time SSO login when
 * the user is provisioned from directory data.
 * - Triggered by: User creation in {@see PersistUserWithUniqueUsername}.
 *
 * **System** — A role was assigned or removed by a programmatic or automated
 * operation without a specific contextual origin (e.g., seeders, test setup).
 */
enum RoleModificationOrigin: string
{
    case UiAction = 'ui-action';
    case RemovedByDeletion = 'removed-by-deletion';
    case NetIdStatusChange = 'netid-status-change';
    case SsoProvisioning = 'sso-provisioning';
    case System = 'system';
}
