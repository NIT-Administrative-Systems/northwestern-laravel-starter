<?php

declare(strict_types=1);

namespace App\Domains\User\Enums;

use App\Domains\User\Models\Concerns\RecordsAuditEvents;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * Every audit event the application records: Eloquent's model events, and the custom events
 * recorded through {@see RecordsAuditEvents}. Each one says how it's shown, so the audit
 * tables, filters, timeline and exports stay in step. A new custom event is a new case.
 *
 * Audits store the event as a string, and an audit can carry one this enum doesn't name, such as
 * one recorded before it existed. The `*For()` methods describe any stored event.
 */
enum AuditEvent: string implements HasColor, HasIcon, HasLabel
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';

    case RoleAssigned = 'role_assigned';
    case RoleRemoved = 'role_removed';
    case PermissionsModified = 'permissions_modified';

    case ServiceClientCreated = 'service_client_created';
    case ServiceClientIpRestrictionsUpdated = 'service_client_ip_restrictions_updated';
    case ServiceClientRevoked = 'service_client_revoked';
    case ApplicationRegistered = 'application_registered';
    case ApplicationUpdated = 'application_updated';
    case ApplicationSecretRegenerated = 'application_secret_regenerated';
    case ApplicationRevoked = 'application_revoked';
    case ApplicationDisconnected = 'application_disconnected';
    case McpClientRevoked = 'mcp_client_revoked';
    case PersonalAccessTokenRevoked = 'personal_access_token_revoked';

    public function getLabel(): string
    {
        return match ($this) {
            self::ServiceClientIpRestrictionsUpdated => 'Service Client IP Restrictions Updated',
            self::McpClientRevoked => 'MCP Client Revoked',
            default => Str::headline($this->value),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Created, self::Restored, self::RoleAssigned, self::ServiceClientCreated, self::ApplicationRegistered => 'success',
            self::Deleted, self::RoleRemoved, self::ServiceClientRevoked, self::ApplicationRevoked, self::ApplicationDisconnected, self::McpClientRevoked, self::PersonalAccessTokenRevoked => 'danger',
            self::Updated, self::PermissionsModified, self::ServiceClientIpRestrictionsUpdated, self::ApplicationUpdated, self::ApplicationSecretRegenerated => 'warning',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Created => Heroicon::OutlinedPlusCircle,
            self::Updated, self::ApplicationUpdated => Heroicon::OutlinedPencilSquare,
            self::Deleted => Heroicon::OutlinedMinusCircle,
            self::Restored => Heroicon::OutlinedArrowUturnLeft,
            self::RoleAssigned => Heroicon::OutlinedUserPlus,
            self::RoleRemoved => Heroicon::OutlinedUserMinus,
            self::PermissionsModified => Heroicon::OutlinedShieldCheck,
            self::ServiceClientCreated => Heroicon::OutlinedKey,
            self::ServiceClientIpRestrictionsUpdated => Heroicon::OutlinedGlobeAlt,
            self::ApplicationRegistered => Heroicon::OutlinedSquaresPlus,
            self::ApplicationSecretRegenerated => Heroicon::OutlinedArrowPath,
            self::ApplicationDisconnected => Heroicon::OutlinedLinkSlash,
            self::ServiceClientRevoked, self::ApplicationRevoked, self::McpClientRevoked, self::PersonalAccessTokenRevoked => Heroicon::OutlinedNoSymbol,
        };
    }

    public static function labelFor(string $event): string
    {
        return self::tryFrom($event)?->getLabel() ?? Str::headline($event);
    }

    public static function colorFor(string $event): string
    {
        return self::tryFrom($event)?->getColor() ?? 'gray';
    }

    public static function iconFor(string $event): Heroicon
    {
        return self::tryFrom($event)?->getIcon() ?? Heroicon::OutlinedTag;
    }

    /**
     * Filter options for these events, or for every event when none are given.
     *
     * @return array<string, string>
     */
    public static function options(self ...$events): array
    {
        return collect($events ?: self::cases())
            ->mapWithKeys(fn (self $event): array => [$event->value => $event->getLabel()])
            ->all();
    }
}
