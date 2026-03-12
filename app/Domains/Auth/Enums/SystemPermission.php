<?php

declare(strict_types=1);

namespace App\Domains\Auth\Enums;

use App\Domains\Auth\Models\Role;
use App\Providers\AppServiceProvider;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

enum SystemPermission: string implements HasLabel
{
    // System Administration

    /**
     * Super-administrator permission that bypasses all authorization checks.
     *
     * A {@see Gate::before()} callback in {@see AppServiceProvider} returns `true` for
     * any user holding this permission, short-circuiting every Gate and Policy check.
     *
     * You never need to check for ManageAll inside a Policy. The `Gate::before` hook
     * fires first and grants access automatically. Adding an explicit check would be
     * redundant and misleading.
     *
     * Use `$user->can(SystemPermission::ManageAll)` (which flows through the Gate) to
     * restrict features to super-administrators only - things that no other permission
     * should ever grant.
     *
     * Use `$user->hasPermissionTo(SystemPermission::ManageAll)` (Spatie direct check,
     * bypasses the Gate) only in infrastructure code where using `can()` would cause
     * recursion — e.g., in {@see Role::canBeManageBy()} where the check must not
     * trigger the before hook.
     */
    case ManageAll = 'manage-all';
    case AccessAdministrationPanel = 'access-administration-panel';
    case ManageImpersonation = 'manage-impersonation';

    // User Management
    case ViewUsers = 'view-users';
    case CreateUsers = 'create-users';
    case EditUsers = 'edit-users';

    // Role Management
    case ViewRoles = 'view-roles';
    case EditRoles = 'edit-roles';
    case DeleteRoles = 'delete-roles';
    case AssignRoles = 'assign-roles';

    // API User Management
    case ManageApiUsers = 'manage-api-users';

    // Audit & Monitoring
    case ViewAuditLogs = 'view-audit-logs';
    case ViewLoginRecords = 'view-login-records';

    // Support
    case ViewSupportTickets = 'view-support-tickets';

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
            self::AccessAdministrationPanel => 'Allows access to the Administration panel.',
            self::ManageImpersonation => 'Allows impersonating other users for troubleshooting and support purposes.',
            self::ManageAll => 'Grants unrestricted administrative control over all resources and operations.',

            // User Management
            self::ViewUsers => 'Allows viewing all user profiles and their details.',
            self::CreateUsers => 'Allows creating new user accounts.',
            self::EditUsers => 'Allows editing existing user profiles and details.',

            // Role Management
            self::ViewRoles => 'Allows viewing all roles and their associated permissions.',
            self::EditRoles => 'Allows creating and editing role definitions and permission assignments.',
            self::DeleteRoles => 'Allows permanently deleting roles from the system.',
            self::AssignRoles => 'Allows assigning, updating, or removing roles from users.',

            // API User Management
            self::ManageApiUsers => 'Allows creating API users and managing their tokens, roles, and access.',

            // Audit & Monitoring
            self::ViewAuditLogs => 'Allows viewing system audit logs and change history.',
            self::ViewLoginRecords => 'Allows viewing user authentication history and login records.',

            // Support
            self::ViewSupportTickets => 'Allows viewing submitted support tickets in the admin panel.',
        };
    }

    /**
     * A system-managed permission is one that is security-sensitive and has unique operational use cases.
     * These permissions are typically only assigned to system administrators, and only users with
     * the {@see self::ManageAll} permission can assign or revoke these permissions from roles.
     */
    public function isSystemManaged(): bool
    {
        return match ($this) {
            self::ManageAll,
            self::AccessAdministrationPanel,
            self::ManageImpersonation,
            self::DeleteRoles,
            self::ViewAuditLogs,
            self::ViewLoginRecords,
            self::ViewSupportTickets => true,
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
            self::ViewUsers => true,
            default => false,
        };
    }

    /**
     * Determines the authorization scope of this permission.
     *
     * The scope indicates whether the permission grants system-wide access (SystemWide)
     * or is limited to resources owned by the user (Personal).
     *
     * ## Default Behavior
     *
     * All permissions in the starter are SystemWide by default, meaning they grant
     * unrestricted system-wide access.
     *
     * ## Adding Personal-Scoped Permissions
     *
     * When adding permissions for self-service functionality (e.g., users managing their
     * own profiles or content), explicitly mark them as Personal scope:
     *
     * ```php
     * return match ($this) {
     *     // Personal-scoped permissions (limited to owned resources)
     *     self::ViewOwnProfile,
     *     self::EditOwnProfile,
     *     self::ViewOwnAuditLogs => PermissionScope::Personal,
     *
     *     // All other permissions default to system-wide access
     *     default => PermissionScope::SystemWide,
     * };
     * ```
     *
     * Remember to implement corresponding ownership checks in your Laravel policies
     * when using personal-scoped permissions.
     *
     * @see PermissionScope For detailed documentation on permission scopes
     */
    public function scope(): PermissionScope
    {
        return match ($this) {
            // When adding personal-scoped permissions, add them here:
            // self::ViewOwnProfile,
            // self::EditOwnProfile => PermissionScope::Personal,

            // All permissions are SystemWide (system-wide) by default
            default => PermissionScope::SystemWide,
        };
    }
}
