<?php

declare(strict_types=1);

namespace App\Domains\Core\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ApiRequestFailureEnum: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    // Authorization Failures
    case INVALID_HEADER_FORMAT = 'invalid-header-format';
    case MISSING_CREDENTIALS = 'missing-credentials';
    case TOKEN_INVALID_OR_EXPIRED = 'token-invalid-or-expired';
    case IP_DENIED = 'ip-denied';

    // General Failures
    case VALIDATION_FAILED = 'validation-failed';
    case CONFLICT = 'conflict';
    case UNAUTHORIZED = 'unauthorized';
    case DATABASE_ERROR = 'database-error';
    case SERVER_ERROR = 'server-error';

    public function getLabel(): string
    {
        return match ($this) {
            self::INVALID_HEADER_FORMAT => 'Invalid Header Format',
            self::MISSING_CREDENTIALS => 'Missing Credentials',
            self::TOKEN_INVALID_OR_EXPIRED => 'Token Invalid or Expired',
            self::IP_DENIED => 'IP Denied',

            self::VALIDATION_FAILED => 'Validation Failed',
            self::CONFLICT => 'Conflict',
            self::UNAUTHORIZED => 'Unauthorized',
            self::DATABASE_ERROR => 'Database Error',
            self::SERVER_ERROR => 'Server Error',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::INVALID_HEADER_FORMAT => 'The Authorization header was missing or was not prefixed with "Bearer ".',
            self::MISSING_CREDENTIALS => 'The Authorization header contained a Bearer scheme but no token was provided.',
            self::TOKEN_INVALID_OR_EXPIRED => 'The Bearer token provided was not found, was expired, or was inactive for the associated user.',
            self::IP_DENIED => 'The client\'s IP address does not match any of the allowed IP addresses or CIDR ranges configured for the matching API token.',

            self::VALIDATION_FAILED => 'The request payload failed validation. One or more fields did not meet the required format, type, or business rules.',
            self::CONFLICT => 'The request could not be completed due to a conflict with the current state of the resource (for example, uniqueness or version conflicts).',
            self::UNAUTHORIZED => 'The request lacks valid authorization for the target resource. This typically indicates missing or invalid permissions.',
            self::DATABASE_ERROR => 'A database error occurred while processing the request. This usually indicates connectivity issues or constraint violations at the persistence layer.',
            self::SERVER_ERROR => 'An unexpected server-side error occurred while handling the request.',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::INVALID_HEADER_FORMAT, self::TOKEN_INVALID_OR_EXPIRED, self::CONFLICT => Heroicon::OutlinedExclamationTriangle,
            self::IP_DENIED => Heroicon::OutlinedNoSymbol,

            self::VALIDATION_FAILED, self::DATABASE_ERROR => Heroicon::OutlinedExclamationCircle,
            default => Heroicon::OutlinedXCircle,
        };
    }

    public function getColor(): string
    {
        return 'danger';
    }
}
