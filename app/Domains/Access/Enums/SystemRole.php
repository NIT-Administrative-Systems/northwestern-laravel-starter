<?php

declare(strict_types=1);

namespace App\Domains\Access\Enums;

/**
 * System roles that are created programmatically and cannot be modified in the UI.
 *
 * They're seeded, and given to people by how they sign in and by system rules.
 */
enum SystemRole: string
{
    /**
     * Given to the NetIDs in SUPER_ADMIN_NETIDS. Its Manage
     * All permission bypasses every authorization check.
     */
    case SuperAdministrator = 'Super Administrator';

    /**
     * The default role, which every Northwestern person gets when they first sign in.
     */
    case NorthwesternUser = 'Northwestern User';
}
