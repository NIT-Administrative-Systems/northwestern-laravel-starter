<?php

declare(strict_types=1);

namespace App\Domains\Core\Formatting;

use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * A count and its noun for a sentence, in Northwestern style: one through nine spelled out,
 * numerals from 10, and a real plural ("one minute", "five seconds", "30 seconds").
 */
final class CountInWords
{
    public static function of(int $count, string $noun): string
    {
        return Number::spell($count, until: 10) . ' ' . Str::plural($noun, $count);
    }
}
