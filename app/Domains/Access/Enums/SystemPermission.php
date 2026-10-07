<?php

declare(strict_types=1);

namespace App\Domains\Access\Enums;

use App\Domains\Access\Models\Role;
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
     * Not on API requests: there a Passport token is limited to its scopes, and the
     * scope, the person's permissions and the policy decide.
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

    // API Access
    case ManageApiAccess = 'manage-api-access';
    case ViewApiRequestLogs = 'view-api-request-logs';
    case CreatePersonalAccessTokens = 'create-personal-access-tokens';
    case UseMcp = 'use-mcp';

    // Audit & Monitoring
    case ViewAuditLogs = 'view-audit-logs';
    case ViewLoginRecords = 'view-login-records';

    // Support
    case ViewSupportTickets = 'view-support-tickets';
    case ManageAnnouncements = 'manage-announcements';

    /**
     * A human-readable label of the permission.
     */
    public function getLabel(): string
    {
        return Str::of($this->value)
            ->replace('-', ' ')
            ->title()
            ->replaceMatches('/\bapi\b/i', 'API')
            ->replaceMatches('/\bmcp\b/i', 'MCP')
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
            self::AccessAdministrationPanel => 'Open the Administration panel.',
            self::ManageImpersonation => 'Impersonate other people to troubleshoot and support them.',
            self::ManageAll => 'Do anything, anywhere in the application.',

            // User Management
            self::ViewUsers => 'View everyone\'s profiles and details.',
            self::CreateUsers => 'Create user accounts.',
            self::EditUsers => 'Edit people\'s profiles and details.',

            // Role Management
            self::ViewRoles => 'View every role and its permissions.',
            self::EditRoles => 'Create and edit roles and their permissions.',
            self::DeleteRoles => 'Delete roles permanently.',
            self::AssignRoles => 'Assign roles to people and remove them.',

            // API Access
            self::ManageApiAccess => 'Manage API users and their service clients; register, edit, and revoke applications, including first-party ones that skip consent; revoke MCP clients; and revoke anyone\'s personal access tokens and connected applications.',
            self::ViewApiRequestLogs => 'View API request logs and usage charts.',
            self::CreatePersonalAccessTokens => 'Create personal access tokens in Account, to use the API as yourself from your own code and tools.',
            self::UseMcp => 'Connect AI clients such as Claude and VS Code to the MCP server, to use its tools as yourself.',

            // Audit & Monitoring
            self::ViewAuditLogs => 'View audit logs of changes to records.',
            self::ViewLoginRecords => 'View sign-in records.',

            // Support
            self::ViewSupportTickets => 'View support requests sent through Contact Support.',
            self::ManageAnnouncements => 'Write, publish, and end announcements for the application\'s users.',
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
     * Whether the permission is also an OAuth scope of the same name (`view-users`). People
     * who hold it can grant it to their personal access tokens and to applications they
     * connect, service clients hold every such scope, and a route requires it with
     * {@see \App\Domains\Api\Http\Middleware\RequireApiScope}. Data access, not interface.
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
