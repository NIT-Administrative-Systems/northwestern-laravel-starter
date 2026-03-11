<?php

declare(strict_types=1);

namespace App\Domains\Core\Database;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Database-agnostic SQL expression builder.
 *
 * Provides driver-specific SQL fragments for operations that differ
 * across PostgreSQL, MySQL, and SQLite.
 */
class DatabaseExpressions
{
    /** @var array<string, string> */
    private static array $timezoneOffsetCache = [];

    /**
     * Get the current database driver name.
     */
    public static function driver(): string
    {
        return DB::getDriverName();
    }

    /**
     * Build a SQL expression to extract the hour from a timestamp in a given timezone.
     *
     * @return array{sql: string, bindings: array<int, string>}
     */
    public static function extractHour(string $column, string $timezone): array
    {
        return match (self::driver()) {
            'mysql' => [
                'sql' => "HOUR(CONVERT_TZ({$column}, 'UTC', ?))",
                'bindings' => [$timezone],
            ],
            'sqlite' => [
                'sql' => "CAST(strftime('%H', {$column}, ?) AS INTEGER)",
                'bindings' => [self::sqliteTimezoneOffset($timezone)],
            ],
            default => [
                'sql' => "EXTRACT(HOUR FROM {$column} AT TIME ZONE 'UTC' AT TIME ZONE ?)",
                'bindings' => [$timezone],
            ],
        };
    }

    /**
     * Build a SQL expression to extract the date from a timestamp in a given timezone.
     *
     * @return array{sql: string, bindings: array<int, string>}
     */
    public static function dateInTimezone(string $column, string $timezone): array
    {
        return match (self::driver()) {
            'mysql' => [
                'sql' => "DATE(CONVERT_TZ({$column}, 'UTC', ?))",
                'bindings' => [$timezone],
            ],
            'sqlite' => [
                'sql' => "DATE({$column}, ?)",
                'bindings' => [self::sqliteTimezoneOffset($timezone)],
            ],
            default => [
                'sql' => "DATE({$column} AT TIME ZONE 'UTC' AT TIME ZONE ?)",
                'bindings' => [$timezone],
            ],
        };
    }

    /**
     * Build a SQL expression for percentile calculation.
     *
     * Returns the SQL fragment for PostgreSQL. For MySQL and SQLite, returns null
     * since percentile must be computed in PHP via computePercentileInPhp().
     *
     * @return array{sql: string|null, bindings: array<int, mixed>}
     */
    public static function percentile(string $column, float $percentile): array
    {
        return match (self::driver()) {
            'pgsql' => [
                'sql' => "PERCENTILE_CONT({$percentile}) WITHIN GROUP (ORDER BY {$column})",
                'bindings' => [],
            ],
            default => [
                'sql' => null,
                'bindings' => [],
            ],
        };
    }

    /**
     * Compute multiple percentile values in PHP from a collection of numeric values.
     *
     * Sorts the collection once and computes all requested percentiles.
     * Uses linear interpolation (consistent with PostgreSQL's PERCENTILE_CONT).
     *
     * @param  Collection<int, mixed>  $values
     * @param  array<int, float>  $percentiles
     * @return array<string, float>
     */
    public static function computeMultiplePercentilesInPhp(Collection $values, array $percentiles): array
    {
        $sorted = $values->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v)->sort()->values();

        $results = [];

        foreach ($percentiles as $p) {
            $results[(string) $p] = self::computePercentileFromSorted($sorted, $p);
        }

        return $results;
    }

    /**
     * Compute a single percentile value in PHP from a collection of numeric values.
     *
     * Uses linear interpolation (consistent with PostgreSQL's PERCENTILE_CONT).
     *
     * @param  Collection<int, mixed>  $values
     */
    public static function computePercentileInPhp(Collection $values, float $percentile): float
    {
        $sorted = $values->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v)->sort()->values();

        return self::computePercentileFromSorted($sorted, $percentile);
    }

    /**
     * Check if the current driver supports native percentile calculation.
     */
    public static function supportsNativePercentile(): bool
    {
        return self::driver() === 'pgsql';
    }

    /**
     * Compute a percentile from a pre-sorted collection using linear interpolation.
     *
     * @param  Collection<int, float>  $sorted
     */
    private static function computePercentileFromSorted(Collection $sorted, float $percentile): float
    {
        if ($sorted->isEmpty()) {
            return 0.0;
        }

        $count = $sorted->count();

        if ($count === 1) {
            return $sorted->first();
        }

        $rank = $percentile * ($count - 1);
        $lowerIndex = (int) floor($rank);
        $upperIndex = (int) ceil($rank);
        $fraction = $rank - $lowerIndex;

        if ($lowerIndex === $upperIndex) {
            return (float) $sorted->get($lowerIndex);
        }

        $lowerValue = (float) $sorted->get($lowerIndex);
        $upperValue = (float) $sorted->get($upperIndex);

        return $lowerValue + $fraction * ($upperValue - $lowerValue);
    }

    /**
     * Convert an IANA timezone name to a UTC offset string for SQLite.
     *
     * SQLite's strftime/date modifiers only accept offset strings like '+05:00' or '-08:00'.
     * Results are cached per timezone to avoid redundant Carbon instantiation.
     */
    private static function sqliteTimezoneOffset(string $timezone): string
    {
        return self::$timezoneOffsetCache[$timezone] ??= Carbon::now($timezone)->format('P');
    }
}
