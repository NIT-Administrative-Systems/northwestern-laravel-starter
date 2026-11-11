<?php

declare(strict_types=1);

namespace App\Domains\Core\Enums;

use Illuminate\Support\Str;

/**
 * Outside services the application calls, named in {@see ServiceDownError}.
 */
enum ExternalService: string
{
    case DirectorySearch = 'directory-search';

    public function label(): string
    {
        return match ($this) {
            // The others are their value in title case; add a case to name one differently.
            default => Str::of($this->value)->replace('-', ' ')->title()->toString(),
        };
    }
}
