<?php

declare(strict_types=1);

namespace App\Domains\User\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

enum PermissionEnum: string implements HasLabel
{
    // General Permissions
    case ACCESS_ADMIN_PANEL = 'access-admin-panel';
    case VIEW_USERS = 'view-users';
    case CREATE_USERS = 'create-users';
    case EDIT_USERS = 'edit-users';
    case VIEW_ROLES = 'view-roles';
    case MODIFY_ROLES = 'modify-roles';
    case MANAGE_USER_ROLES = 'manage-user-roles';

    // System Managed Permissions
    case MANAGE_ALL = 'manage-all';

    case MANAGE_IMPERSONATION = 'manage-impersonation';
    case VIEW_AUDIT_LOGS = 'view-audit-logs';
    case VIEW_LOGIN_RECORDS = 'view-login-records';
    case MANAGE_API_USERS = 'manage-api-users';
    case DELETE_ROLES = 'delete-roles';

    /**
     * A human-readable label of the permission.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::MANAGE_API_USERS => 'Manage API Users',
            // Auto-converts the string to a title. You can override one by adding a specific case.
            default => Str::of($this->value)->replace('-', ' ')->title()->toString(),
        };
    }

    /**
     * A short description of the permission. This is used in the UI to describe what the permission
     * allows the user to do.
     */
    public function description(): string
    {
        return match ($this) {
            // General Permissions
            self::ACCESS_ADMIN_PANEL => 'Provides entry to the administration dashboard.',
            self::VIEW_USERS => 'Allows viewing of all user profiles.',
            self::CREATE_USERS => 'Enables creation of new user profiles.',
            self::EDIT_USERS => 'Permits editing of existing user profiles.',
            self::VIEW_ROLES => 'Allows viewing of all defined roles and their association permissions.',
            self::MODIFY_ROLES => 'Enables creation and modification of role definitions and permission sets.',
            self::MANAGE_USER_ROLES => 'Grants the ability to assign, update, or remove roles from users.',
            // System Managed Permissions
            self::MANAGE_ALL => 'Provides unrestricted administrative control over all resources and operations.',
            self::MANAGE_IMPERSONATION => 'Allows authorized users to impersonate other accounts for troubleshooting and support.',
            self::VIEW_AUDIT_LOGS => 'Grants access to view system generated audit logs.',
            self::VIEW_LOGIN_RECORDS => 'Allows viewing of user authentication history.',
            self::MANAGE_API_USERS => 'Grants the ability to create API users and manage their roles and API tokens.',
            self::DELETE_ROLES => 'Permits permanent deletion of roles.',
        };
    }

    /**
     * A system-managed permission is one that is security-sensitive and has unique operational use cases.
     * These permissions are typically only assigned to system administrators, and only users with
     * the {@see self::MANAGE_ALL} permission can assign or revoke these permissions from roles.
     */
    public function isSystemManaged(): bool
    {
        return match ($this) {
            /** @phpstan-ignore-next-line  */
            self::MANAGE_ALL, self::DELETE_ROLES, self::MANAGE_IMPERSONATION, self::VIEW_AUDIT_LOGS, self::VIEW_LOGIN_RECORDS => true,
            default => false,
        };
    }

    /**
     * An API-relevant permission is one that makes sense for API integrations to have.
     * These are typically data access permissions rather than UI-specific permissions.
     */
    public function isApiRelevant(): bool
    {
        return match ($this) {
            self::VIEW_USERS => true,
            default => false,
        };
    }
}
