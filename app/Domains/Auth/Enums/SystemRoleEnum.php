<?php

declare(strict_types=1);

namespace App\Domains\Auth\Enums;

/**
 * System roles that are created programmatically and cannot be modified in the UI.
 *
 * These roles are seeded into the database and automatically assigned based on user
 * authentication type and system rules.
 */
enum SystemRoleEnum: string
{
    /**
     * Assigned to super administrators defined in the SUPER_ADMIN_NETIDS environment variable.
     * Grants unrestricted access to all application features and bypasses authorization.
     */
    case SUPER_ADMINISTRATOR = 'Super Administrator';

    /**
     * Default role automatically assigned to Northwestern users upon SSO authentication.
     * Provides baseline access for all authenticated Northwestern users.
     */
    case NORTHWESTERN_USER = 'Northwestern User';
}
