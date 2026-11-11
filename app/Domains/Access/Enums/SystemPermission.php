<?php

declare(strict_types=1);

namespace App\Domains\Access\Enums;

use App\Providers\AppServiceProvider;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

enum SystemPermission: string implements HasLabel
{
    // System Administration

    /**
     * Bypasses every authorization check. {@see AppServiceProvider} allows anyone who
     * holds it in a {@see Gate::before()} callback, before any gate or policy runs.
     *
     * Not on API requests, where a token is held to its scopes, and
     * the scope, the person's permissions and the policy decide.
     *
     * A policy never needs to check for it.
     *
     * For a feature only super administrators may use,
     * ask `$user->can(SystemPermission::ManageAll)`.
     *
     * `$user->hasPermissionTo()` checks it without the gate, for
     * the code where `can()` would recurse into that callback.
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
     * Get the sentence the administration panel shows for what the permission allows.
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
     * Determine whether the permission is system-managed: so sensitive that only
     * someone with {@see self::ManageAll} may add it to a role or remove it.
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
     * Determine whether the permission is also an OAuth scope of the same
     * name (`view-users`), for data access rather than the interface.
     *
     * People who hold it can grant it to their personal access tokens and the
     * applications they connect, and service clients hold every such scope.
     *
     * Routes require it with {@see \App\Domains\Api\Http\Middleware\RequireApiScope}.
     */
    public function isApiRelevant(): bool
    {
        return match ($this) {
            self::ViewUsers => true,
            default => false,
        };
    }

    /**
     * Get how far the permission reaches. Every one the starter ships is system-wide.
     *
     * Mark a self-service permission, which reaches only the person's
     * own records, as personal, and check ownership in its policy:
     *
     * ```php
     * return match ($this) {
     *     self::ViewOwnProfile,
     *     self::EditOwnProfile => PermissionScope::Personal,
     *     default => PermissionScope::SystemWide,
     * };
     * ```
     *
     * @see PermissionScope
     */
    public function scope(): PermissionScope
    {
        return PermissionScope::SystemWide;
    }
}
