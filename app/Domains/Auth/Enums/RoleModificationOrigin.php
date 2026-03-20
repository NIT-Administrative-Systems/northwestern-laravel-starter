<?php

declare(strict_types=1);

namespace App\Domains\Auth\Enums;

use App\Domains\User\Actions\PersistUserWithUniqueUsername;
use App\Domains\User\Listeners\ProcessNetIdUpdate;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

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
enum RoleModificationOrigin: string implements HasColor, HasIcon, HasLabel
{
    case UiAction = 'ui-action';
    case RemovedByDeletion = 'removed-by-deletion';
    case NetIdStatusChange = 'netid-status-change';
    case SsoProvisioning = 'sso-provisioning';
    case System = 'system';

    public function getLabel(): string
    {
        return match ($this) {
            self::UiAction => 'UI Action',
            self::SsoProvisioning => 'SSO Provisioning',
            self::NetIdStatusChange => 'NetID Event',
            self::RemovedByDeletion => 'Role Deleted',
            self::System => 'System',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::UiAction => 'gray',
            self::SsoProvisioning => 'info',
            self::NetIdStatusChange => 'warning',
            self::RemovedByDeletion => 'danger',
            self::System => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::UiAction => Heroicon::OutlinedCursorArrowRays,
            self::SsoProvisioning => Heroicon::OutlinedCloudArrowDown,
            self::NetIdStatusChange => Heroicon::OutlinedIdentification,
            self::RemovedByDeletion => Heroicon::OutlinedTrash,
            self::System => Heroicon::OutlinedCog6Tooth,
        };
    }
}
