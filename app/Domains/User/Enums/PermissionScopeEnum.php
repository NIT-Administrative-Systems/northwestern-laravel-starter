<?php

declare(strict_types=1);

namespace App\Domains\User\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

/**
 * Defines the authorization scope of permissions to distinguish between system-wide
 * and contextually-limited access.
 *
 * Permission scopes provide visual and logical distinctions in the role management UI,
 * helping administrators understand the breadth of access granted by each permission.
 *
 * ## Scope Types
 *
 * **SYSTEM_WIDE**: System-wide permissions that grant unrestricted access across all resources.
 * - Example: `view-users` allows viewing ALL users in the system
 * - Example: `edit-roles` allows editing ANY role
 * - Typically assigned to administrators and staff
 *
 * **PERSONAL**: Permissions limited to resources owned by or directly related to the user.
 * - Example: `view-own-profile` allows viewing only the user's own profile
 * - Example: `edit-own-posts` allows editing only posts created by the user
 * - Enables self-service functionality without granting broad system access
 *
 * ## Usage in Policies
 *
 * When implementing Laravel policies, personal-scoped permissions should include
 * ownership checks:
 *
 * ```php
 * public function update(User $user, Post $post): bool
 * {
 *     // SYSTEM_WIDE permission bypasses ownership check
 *     if ($user->hasPermissionTo('edit-any-post')) {
 *         return true;
 *     }
 *
 *     // PERSONAL permission requires ownership verification
 *     return $user->hasPermissionTo('edit-own-post') && $post->user->is($user);
 * }
 * ```
 *
 * @see PermissionEnum::scope() For assigning scopes to individual permissions
 */
enum PermissionScopeEnum: string implements HasLabel
{
    case SYSTEM_WIDE = 'system-wide';
    case PERSONAL = 'personal';

    /**
     * A human-readable label of the permission.
     */
    public function getLabel(): string
    {
        return Str::of($this->value)->replace('-', ' ')->title()->toString();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SYSTEM_WIDE => 'gray',
            self::PERSONAL => 'info',
        };
    }

    public function getBadgeHTML(): string
    {
        return Blade::render('<x-filament::badge class="ml-2" size="sm" color="{{ $color }}">{{ $name }}</x-filament::badge>', [
            'name' => $this->getLabel(),
            'color' => $this->getColor() ?: 'gray',
        ]);
    }
}
