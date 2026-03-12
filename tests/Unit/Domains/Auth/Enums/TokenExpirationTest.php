<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Auth\Enums;

use App\Domains\Auth\Enums\TokenExpiration;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenExpiration::class)]
class TokenExpirationTest extends TestCase
{
    #[DataProvider('labelProvider')]
    public function test_get_label(TokenExpiration $enum, string $expected): void
    {
        $this->assertSame($expected, $enum->getLabel());
    }

    /** @return array<string, array{0: TokenExpiration, 1: string}> */
    public static function labelProvider(): array
    {
        return [
            'one day' => [TokenExpiration::OneDay, '1 Day'],
            'one week' => [TokenExpiration::OneWeek, '7 Days'],
            'one month' => [TokenExpiration::OneMonth, '30 Days'],
            'two months' => [TokenExpiration::TwoMonths, '60 Days'],
            'three months' => [TokenExpiration::ThreeMonths, '90 Days'],
            'six months' => [TokenExpiration::SixMonths, '180 Days'],
            'one year' => [TokenExpiration::OneYear, '1 Year'],
            'never' => [TokenExpiration::Never, 'No Expiration'],
        ];
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

        $this->assertNotNull($result);
        $this->assertTrue(
            $expected->equalTo($result),
            "Expected {$expectedDays} days added for {$enum->name}"
        );
    }

    /** @return array<string, array{0: TokenExpiration, 1: int}> */
    public static function expiresAtProvider(): array
    {
        return [
            'one day' => [TokenExpiration::OneDay, 1],
            'one week' => [TokenExpiration::OneWeek, 7],
            'one month' => [TokenExpiration::OneMonth, 30],
            'two months' => [TokenExpiration::TwoMonths, 60],
            'three months' => [TokenExpiration::ThreeMonths, 90],
            'six months' => [TokenExpiration::SixMonths, 180],
            'one year' => [TokenExpiration::OneYear, 365],
        ];
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
