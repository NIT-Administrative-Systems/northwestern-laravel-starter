<?php

declare(strict_types=1);

namespace App\Domains\Auth\Enums;

use App\Domains\Auth\Models\Role;
use App\Providers\AppServiceProvider;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

enum PermissionEnum: string implements HasLabel
{
    // System Administration

    /**
     * Super-administrator permission that bypasses all authorization checks.
     *
     * A {@see Gate::before()} callback in {@see AppServiceProvider} returns `true` for
     * any user holding this permission, short-circuiting every Gate and Policy check.
     *
     * You never need to check for MANAGE_ALL inside a Policy. The `Gate::before` hook
     * fires first and grants access automatically. Adding an explicit check would be
     * redundant and misleading.
     *
     * Use `$user->can(PermissionEnum::MANAGE_ALL)` (which flows through the Gate) to
     * restrict features to super-administrators only - things that no other permission
     * should ever grant. Examples from this codebase:
     *
     * - **Blade/Livewire conditionals:** `@can(PermissionEnum::MANAGE_ALL)` to show
     *   controls that only super-admins should see.
     * - **Filament visibility:** `->visible(fn () => auth()->user()->can(...))` to
     *   gate an action or page to super-admins.
     * - **Telescope access:** The `viewTelescope` gate is defined as
     *   `$user->can(PermissionEnum::MANAGE_ALL)`.
     *
     * In these cases, `can()` is preferred because it goes through the Gate and is
     * consistent with how the rest of the authorization system works.
     *
     * Use `$user->hasPermissionTo(PermissionEnum::MANAGE_ALL)` (Spatie direct check,
     * bypasses the Gate) only in infrastructure code where using `can()` would cause
     * recursion — e.g., in {@see Role::canBeManageBy()} where the check must not
     * trigger the before hook.
     */
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

    // Support
    case VIEW_SUPPORT_TICKETS = 'view-support-tickets';

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

            // Support
            self::VIEW_SUPPORT_TICKETS => 'Allows viewing submitted support tickets in the admin panel.',
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
            self::VIEW_LOGIN_RECORDS,
            self::VIEW_SUPPORT_TICKETS => true,
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

    /**
     * Determines the authorization scope of this permission.
     *
     * The scope indicates whether the permission grants system-wide access (SYSTEM_WIDE)
     * or is limited to resources owned by the user (PERSONAL).
     *
     * ## Default Behavior
     *
     * All permissions in the starter are SYSTEM_WIDE by default, meaning they grant
     * unrestricted system-wide access.
     *
     * ## Adding Personal-Scoped Permissions
     *
     * When adding permissions for self-service functionality (e.g., users managing their
     * own profiles or content), explicitly mark them as PERSONAL scope:
     *
     * ```php
     * return match ($this) {
     *     // Personal-scoped permissions (limited to owned resources)
     *     self::VIEW_OWN_PROFILE,
     *     self::EDIT_OWN_PROFILE,
     *     self::VIEW_OWN_AUDIT_LOGS => PermissionScopeEnum::PERSONAL,
     *
     *     // All other permissions default to system-wide access
     *     default => PermissionScopeEnum::SYSTEM_WIDE,
     * };
     * ```
     *
     * Remember to implement corresponding ownership checks in your Laravel policies
     * when using personal-scoped permissions.
     *
     * @see PermissionScopeEnum For detailed documentation on permission scopes
     */
    public function scope(): PermissionScopeEnum
    {
        return match ($this) {
            // When adding personal-scoped permissions, add them here:
            // self::VIEW_OWN_PROFILE,
            // self::EDIT_OWN_PROFILE => PermissionScopeEnum::PERSONAL,

            // All permissions are SYSTEM_WIDE (system-wide) by default
            default => PermissionScopeEnum::SYSTEM_WIDE,
        };
    }
}
