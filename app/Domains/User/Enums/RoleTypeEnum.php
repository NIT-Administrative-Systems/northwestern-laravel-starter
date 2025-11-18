<?php

declare(strict_types=1);

namespace App\Domains\User\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

enum RoleTypeEnum: string implements HasColor, HasIcon, HasLabel
{
    case SYSTEM_MANAGED = 'system-managed';
    case APPLICATION_ADMIN = 'application-admin';
    case APPLICATION_ROLE = 'application-role';
    case API_INTEGRATION = 'api-integration';

    public function getLabel(): string
    {
        return match ($this) {
            self::API_INTEGRATION => 'API Integration',
            // Auto-converts the string to a title. You can override one by adding a specific case.
            default => Str::of($this->value)->replace('-', ' ')->title()->toString(),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SYSTEM_MANAGED => 'danger',
            self::APPLICATION_ADMIN => 'warning',
            self::APPLICATION_ROLE => 'success',
            self::API_INTEGRATION => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::SYSTEM_MANAGED => Heroicon::OutlinedShieldCheck,
            self::APPLICATION_ADMIN => Heroicon::OutlinedUserGroup,
            self::APPLICATION_ROLE => Heroicon::OutlinedUser,
            self::API_INTEGRATION => Heroicon::OutlinedCog,
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::SYSTEM_MANAGED => 'System-level administrators with full platform access.',
            self::APPLICATION_ADMIN => 'Application administrators who manage specific areas.',
            self::APPLICATION_ROLE => 'Standard user roles with specific permissions.',
            self::API_INTEGRATION => 'Roles for API consumers and integrations.',
        };
    }
}
