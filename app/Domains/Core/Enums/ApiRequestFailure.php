<?php

declare(strict_types=1);

namespace App\Domains\Core\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ApiRequestFailure: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    // Authorization Failures
    case InvalidHeaderFormat = 'invalid-header-format';
    case MissingCredentials = 'missing-credentials';
    case TokenInvalidOrExpired = 'token-invalid-or-expired';
    case IpDenied = 'ip-denied';

    // General Failures
    case ValidationFailed = 'validation-failed';
    case Conflict = 'conflict';
    case Unauthorized = 'unauthorized';
    case DatabaseError = 'database-error';
    case ServerError = 'server-error';

    public function getLabel(): string
    {
        return match ($this) {
            self::InvalidHeaderFormat => 'Invalid Header Format',
            self::MissingCredentials => 'Missing Credentials',
            self::TokenInvalidOrExpired => 'Token Invalid or Expired',
            self::IpDenied => 'IP Denied',

            self::ValidationFailed => 'Validation Failed',
            self::Conflict => 'Conflict',
            self::Unauthorized => 'Unauthorized',
            self::DatabaseError => 'Database Error',
            self::ServerError => 'Server Error',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::InvalidHeaderFormat => 'The Authorization header was missing or was not prefixed with "Bearer ".',
            self::MissingCredentials => 'The Authorization header contained a Bearer scheme but no token was provided.',
            self::TokenInvalidOrExpired => 'The Bearer token provided was not found, was expired, or was inactive for the associated user.',
            self::IpDenied => 'The client\'s IP address does not match any of the allowed IP addresses or CIDR ranges configured for the matching Access Token.',

            self::ValidationFailed => 'The request payload failed validation. One or more fields did not meet the required format, type, or business rules.',
            self::Conflict => 'The request could not be completed due to a conflict with the current state of the resource (for example, uniqueness or version conflicts).',
            self::Unauthorized => 'The request lacks valid authorization for the target resource. This typically indicates missing or invalid permissions.',
            self::DatabaseError => 'A database error occurred while processing the request. This usually indicates connectivity issues or constraint violations at the persistence layer.',
            self::ServerError => 'An unexpected server-side error occurred while handling the request.',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::InvalidHeaderFormat, self::TokenInvalidOrExpired, self::Conflict => Heroicon::OutlinedExclamationTriangle,
            self::IpDenied => Heroicon::OutlinedNoSymbol,

            self::ValidationFailed, self::DatabaseError => Heroicon::OutlinedExclamationCircle,
            default => Heroicon::OutlinedXCircle,
        };
    }

    public function getColor(): string
    {
        return 'danger';
    }
}
