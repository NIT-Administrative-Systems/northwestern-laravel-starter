<?php

declare(strict_types=1);

namespace App\Domains\Auth\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

enum RoleTypeEnum: string implements HasColor, HasIcon, HasLabel
{
    case SystemManaged = 'system-managed';
    case ApplicationAdmin = 'application-admin';
    case ApplicationRole = 'application-role';
    case ApiIntegration = 'api-integration';

    public function getLabel(): string
    {
        return match ($this) {
            self::ApiIntegration => 'API Integration',
            // Auto-converts the string to a title. You can override one by adding a specific case.
            default => Str::of($this->value)->replace('-', ' ')->title()->toString(),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SystemManaged => 'danger',
            self::ApplicationAdmin => 'warning',
            self::ApplicationRole => 'success',
            self::ApiIntegration => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::SystemManaged => Heroicon::OutlinedShieldCheck,
            self::ApplicationAdmin => Heroicon::OutlinedUserGroup,
            self::ApplicationRole => Heroicon::OutlinedUser,
            self::ApiIntegration => Heroicon::OutlinedCog,
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::SystemManaged => 'Roles that are programmatically managed by the system.',
            self::ApplicationAdmin => 'Application administrators who manage specific areas.',
            self::ApplicationRole => 'Standard user roles with specific permissions.',
            self::ApiIntegration => 'Roles for API consumers and integrations.',
        };
    }
}
