<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Auth\Enums;

use App\Domains\Auth\Enums\TokenExpiration;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenExpiration::class)]
final class TokenExpirationTest extends TestCase
{
    #[DataProvider('labelProvider')]
    public function test_get_label(TokenExpiration $enum, string $expected): void
    {
        $this->assertSame($expected, $enum->getLabel());
    }

    /** @return \Iterator<string, array{TokenExpiration, string}> */
    public static function labelProvider(): \Iterator
    {
        yield 'one day' => [TokenExpiration::OneDay, '1 Day'];
        yield 'one week' => [TokenExpiration::OneWeek, '7 Days'];
        yield 'one month' => [TokenExpiration::OneMonth, '30 Days'];
        yield 'two months' => [TokenExpiration::TwoMonths, '60 Days'];
        yield 'three months' => [TokenExpiration::ThreeMonths, '90 Days'];
        yield 'six months' => [TokenExpiration::SixMonths, '180 Days'];
        yield 'one year' => [TokenExpiration::OneYear, '1 Year'];
        yield 'never' => [TokenExpiration::Never, 'No Expiration'];
    }

    public function test_never_expires_at_returns_null(): void
    {
        $this->assertNull(TokenExpiration::Never->expiresAt());
    }

    public function test_never_expires_at_returns_null_even_with_from_date(): void
    {
        $from = Carbon::parse('2026-01-01');

        $this->assertNull(TokenExpiration::Never->expiresAt($from));
    }

    #[DataProvider('expiresAtProvider')]
    public function test_expires_at_adds_correct_days(TokenExpiration $enum, int $expectedDays): void
    {
        $from = Carbon::parse('2026-01-01 00:00:00');
        $expected = Carbon::parse('2026-01-01 00:00:00')->addDays($expectedDays);
        $result = $enum->expiresAt($from);

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertTrue(
            $expected->equalTo($result),
            "Expected {$expectedDays} days added for {$enum->name}"
        );
    }

    /** @return \Iterator<string, array{TokenExpiration, int}> */
    public static function expiresAtProvider(): \Iterator
    {
        yield 'one day' => [TokenExpiration::OneDay, 1];
        yield 'one week' => [TokenExpiration::OneWeek, 7];
        yield 'one month' => [TokenExpiration::OneMonth, 30];
        yield 'two months' => [TokenExpiration::TwoMonths, 60];
        yield 'three months' => [TokenExpiration::ThreeMonths, 90];
        yield 'six months' => [TokenExpiration::SixMonths, 180];
        yield 'one year' => [TokenExpiration::OneYear, 365];
    }

    public function test_expires_at_defaults_to_now_when_no_from_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));

        $result = TokenExpiration::OneWeek->expiresAt();

        $this->assertNotNull($result);
        $this->assertTrue(Carbon::parse('2026-06-22 12:00:00')->equalTo($result));

        Carbon::setTestNow();
    }
}
