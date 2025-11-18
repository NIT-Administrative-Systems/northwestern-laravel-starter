<?php

declare(strict_types=1);

namespace App\Domains\Core\Enums;

use Illuminate\Support\Str;

/**
 * The application might need to interact with external services. Define them here to make interacting
 * with APIs and throwing exceptions more manageable.
 */
enum ExternalServiceEnum: string
{
    case DIRECTORY_SEARCH = 'directory-search';

    /**
     * A human-readable label of the external service.
     */
    public function label(): string
    {
        return match ($this) {
            // Auto-converts the string to a title. You can override one by adding a specific case.
            default => Str::of($this->value)->replace('-', ' ')->title()->toString(),
        };
    }
}
