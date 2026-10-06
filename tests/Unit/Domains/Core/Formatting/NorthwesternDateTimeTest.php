<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Core\Formatting;

use App\Domains\Core\Formatting\NorthwesternDateTime;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(NorthwesternDateTime::class)]
final class NorthwesternDateTimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-06 12:00', 'America/Chicago'));
    }

    /**
     * @return \Iterator<string, array{string, string}>
     */
    public static function times(): \Iterator
    {
        yield 'minutes' => ['2026-10-10 10:12', '10:12 a.m.'];
        yield 'on the hour' => ['2026-10-10 16:00', '4 p.m.'];
        yield 'noon' => ['2026-10-10 12:00', 'noon'];
        yield 'midnight' => ['2026-10-10 00:00', 'midnight'];
    }

    #[DataProvider('times')]
    public function test_times_follow_northwestern_style(string $moment, string $expected): void
    {
        $this->assertSame($expected, NorthwesternDateTime::time(CarbonImmutable::parse($moment, 'America/Chicago'), 'America/Chicago'));
    }

    public function test_the_time_comes_before_the_date_and_this_year_is_left_out(): void
    {
        $moment = CarbonImmutable::parse('2026-10-10 15:12', 'UTC');

        $this->assertSame('10:12 a.m. CDT Saturday, October 10', NorthwesternDateTime::format($moment, 'America/Chicago'));
        $this->assertSame('10:12 a.m. Saturday, October 10', NorthwesternDateTime::format($moment, 'America/Chicago', withZone: false));
    }

    public function test_another_year_is_spelled_out(): void
    {
        $moment = CarbonImmutable::parse('2027-01-04 09:00', 'America/Chicago');

        $this->assertSame('Monday, January 4, 2027', NorthwesternDateTime::date($moment, 'America/Chicago'));
        $this->assertSame('January 4, 2027', NorthwesternDateTime::date($moment, 'America/Chicago', withWeekday: false));
    }

    public function test_it_defaults_to_the_application_timezone(): void
    {
        config(['app.timezone' => 'America/Chicago']);

        $this->assertSame('9 a.m.', NorthwesternDateTime::time(CarbonImmutable::parse('2026-10-10 14:00', 'UTC')));
    }
}
