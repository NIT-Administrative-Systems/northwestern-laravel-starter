<?php

declare(strict_types=1);

namespace App\Domains\Access\Enums;

use App\Domains\Access\Actions\AddPersonToRole;
use App\Domains\User\Actions\PersistUserWithUniqueUsername;
use App\Domains\User\Listeners\ProcessNetIdUpdate;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Why a person's roles changed, stored as a tag on the role audit.
 */
enum RoleModificationOrigin: string implements HasColor, HasIcon, HasLabel
{
    /** An administrator assigned or removed the role in the administration panel. */
    case UiAction = 'ui-action';

    /** {@see AddPersonToRole} gave it to someone on a pasted or uploaded list. */
    case BulkAssignment = 'bulk-assignment';

    /** The role was deleted, which removes it from everyone who held it. */
    case RemovedByDeletion = 'removed-by-deletion';

    /** {@see ProcessNetIdUpdate} removed it when the person's NetID was deactivated. */
    case NetIdStatusChange = 'netid-status-change';

    /** {@see PersistUserWithUniqueUsername} gave it when a person first signed in. */
    case SsoProvisioning = 'sso-provisioning';

    /** Code changed it with no other origin to give, such as a seeder or a test. */
    case System = 'system';

    public function getLabel(): string
    {
        return match ($this) {
            self::UiAction => 'UI Action',
            self::BulkAssignment => 'Bulk Add',
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
            self::BulkAssignment => 'info',
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
            self::BulkAssignment => Heroicon::OutlinedUserGroup,
            self::SsoProvisioning => Heroicon::OutlinedCloudArrowDown,
            self::NetIdStatusChange => Heroicon::OutlinedIdentification,
            self::RemovedByDeletion => Heroicon::OutlinedTrash,
            self::System => Heroicon::OutlinedCog6Tooth,
        };
    }
}
