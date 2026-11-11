<?php

declare(strict_types=1);

namespace App\Domains\Access\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

/**
 * How far a permission reaches: every record, or only what the person owns.
 *
 * A system-wide permission such as `view-users` reaches every record.
 *
 * A personal one such as `edit-own-post` reaches only the
 * person's own, so its policy checks ownership as well:
 *
 * ```php
 * public function update(User $user, Post $post): bool
 * {
 *     // SystemWide permission bypasses ownership check
 *     if ($user->hasPermissionTo('edit-any-post')) {
 *         return true;
 *     }
 *
 *     // Personal permission requires ownership verification
 *     return $user->hasPermissionTo('edit-own-post') && $post->user->is($user);
 * }
 * ```
 *
 * @see SystemPermission::scope()
 */
enum PermissionScope: string implements HasLabel
{
    case SystemWide = 'system-wide';
    case Personal = 'personal';

    public function getLabel(): string
    {
        return Str::of($this->value)->replace('-', ' ')->title()->toString();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SystemWide => 'gray',
            self::Personal => 'info',
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
