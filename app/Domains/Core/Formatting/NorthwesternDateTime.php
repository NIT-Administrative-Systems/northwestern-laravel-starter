<?php

declare(strict_types=1);

namespace App\Domains\Core\Formatting;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Dates and times in sentences, in Northwestern style: "a.m." and "p.m.", no ":00", "noon" and
 * "midnight", the time before the date, months spelled out, and the year only when it isn't the
 * current one: "10:12 a.m. CDT Saturday, October 10". Tables and other compact displays keep
 * their own formats.
 *
 * @see https://www.northwestern.edu/brand/editorial-guidelines/style-guide/
 */
final class NorthwesternDateTime
{
    /**
     * The time, then the day and date: "10:12 a.m. CDT Saturday, October 10".
     */
    public static function format(CarbonInterface $moment, ?string $timezone = null, bool $withZone = true): string
    {
        return self::time($moment, $timezone, $withZone) . ' ' . self::date($moment, $timezone);
    }

    /**
     * "10:12 a.m.", "4 p.m.", "noon" or "midnight", with the zone abbreviation when asked.
     */
    public static function time(CarbonInterface $moment, ?string $timezone = null, bool $withZone = false): string
    {
        $local = self::local($moment, $timezone);

        $time = match ($local->format('H:i')) {
            '12:00' => 'noon',
            '00:00' => 'midnight',
            default => str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $local->format($local->minute === 0 ? 'g a' : 'g:i a')),
        };

        return $withZone ? "{$time} {$local->format('T')}" : $time;
    }

    /**
     * "Saturday, October 10", with ", 2027" when it isn't this year.
     */
    public static function date(CarbonInterface $moment, ?string $timezone = null, bool $withWeekday = true): string
    {
        $local = self::local($moment, $timezone);
        $format = ($withWeekday ? 'l, ' : '') . 'F j';

        return $local->year === Carbon::now($local->getTimezone())->year ? $local->format($format) : $local->format("{$format}, Y");
    }

    private static function local(CarbonInterface $moment, ?string $timezone): CarbonInterface
    {
        return $moment->copy()->setTimezone($timezone ?? config('app.timezone'));
    }
}
