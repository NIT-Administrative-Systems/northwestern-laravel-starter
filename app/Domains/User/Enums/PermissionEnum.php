<?php

declare(strict_types=1);

namespace App\Domains\User\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

enum PermissionEnum: string implements HasLabel
{
    // System Administration
    case MANAGE_ALL = 'manage-all';
    case ACCESS_ADMINISTRATION_PANEL = 'access-administration-panel';
    case MANAGE_IMPERSONATION = 'manage-impersonation';

    // User Management
    case VIEW_USERS = 'view-users';
    case CREATE_USERS = 'create-users';
    case EDIT_USERS = 'edit-users';

    // Role Management
    case VIEW_ROLES = 'view-roles';
    case EDIT_ROLES = 'edit-roles';
    case DELETE_ROLES = 'delete-roles';
    case ASSIGN_ROLES = 'assign-roles';

    // API User Management
    case MANAGE_API_USERS = 'manage-api-users';

    // Audit & Monitoring
    case VIEW_AUDIT_LOGS = 'view-audit-logs';
    case VIEW_LOGIN_RECORDS = 'view-login-records';
    case MANAGE_API_USERS = 'manage-api-users';
    case DELETE_ROLES = 'delete-roles';

    /**
     * A human-readable label of the permission.
     */
    public function getLabel(): string
    {
        return Str::of($this->value)
            ->replace('-', ' ')
            ->title()
            ->replaceMatches('/\bapi\b/i', 'API')
            ->toString();
    }

    /**
     * A short description of the permission. This is used in the UI to describe what the permission
     * allows the user to do.
     */
    public function description(): string
    {
        return match ($this) {
            // System Administration
            self::ACCESS_ADMINISTRATION_PANEL => 'Allows access to the Administration panel.',
            self::MANAGE_IMPERSONATION => 'Allows impersonating other users for troubleshooting and support purposes.',
            self::MANAGE_ALL => 'Grants unrestricted administrative control over all resources and operations.',

            // User Management
            self::VIEW_USERS => 'Allows viewing all user profiles and their details.',
            self::CREATE_USERS => 'Allows creating new user accounts.',
            self::EDIT_USERS => 'Allows editing existing user profiles and details.',

            // Role Management
            self::VIEW_ROLES => 'Allows viewing all roles and their associated permissions.',
            self::EDIT_ROLES => 'Allows creating and editing role definitions and permission assignments.',
            self::DELETE_ROLES => 'Allows permanently deleting roles from the system.',
            self::ASSIGN_ROLES => 'Allows assigning, updating, or removing roles from users.',

            // API User Management
            self::MANAGE_API_USERS => 'Allows creating API users and managing their tokens, roles, and access.',

            // Audit & Monitoring
            self::VIEW_AUDIT_LOGS => 'Allows viewing system audit logs and change history.',
            self::VIEW_LOGIN_RECORDS => 'Allows viewing user authentication history and login records.',
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
            self::MANAGE_ALL,
            self::ACCESS_ADMINISTRATION_PANEL,
            self::MANAGE_IMPERSONATION,
            self::DELETE_ROLES,
            self::VIEW_AUDIT_LOGS,
            self::VIEW_LOGIN_RECORDS => true,
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
