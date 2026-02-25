<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Auth\Enums;

use App\Domains\Auth\Enums\TokenExpirationEnum;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenExpirationEnum::class)]
class TokenExpirationEnumTest extends TestCase
{
    #[DataProvider('labelProvider')]
    public function test_get_label(TokenExpirationEnum $enum, string $expected): void
    {
        $this->assertSame($expected, $enum->getLabel());
    }

    /** @return array<string, array{0: TokenExpirationEnum, 1: string}> */
    public static function labelProvider(): array
    {
        return [
            'one day' => [TokenExpirationEnum::ONE_DAY, '1 Day'],
            'one week' => [TokenExpirationEnum::ONE_WEEK, '7 Days'],
            'one month' => [TokenExpirationEnum::ONE_MONTH, '30 Days'],
            'two months' => [TokenExpirationEnum::TWO_MONTHS, '60 Days'],
            'three months' => [TokenExpirationEnum::THREE_MONTHS, '90 Days'],
            'six months' => [TokenExpirationEnum::SIX_MONTHS, '180 Days'],
            'one year' => [TokenExpirationEnum::ONE_YEAR, '1 Year'],
            'never' => [TokenExpirationEnum::NEVER, 'No Expiration'],
        ];
    }

    public function test_never_expires_at_returns_null(): void
    {
        $this->assertNull(TokenExpirationEnum::NEVER->expiresAt());
    }

    public function test_never_expires_at_returns_null_even_with_from_date(): void
    {
        $from = Carbon::parse('2026-01-01');

        $this->assertNull(TokenExpirationEnum::NEVER->expiresAt($from));
    }

    #[DataProvider('expiresAtProvider')]
    public function test_expires_at_adds_correct_days(TokenExpirationEnum $enum, int $expectedDays): void
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

    /** @return array<string, array{0: TokenExpirationEnum, 1: int}> */
    public static function expiresAtProvider(): array
    {
        return [
            'one day' => [TokenExpirationEnum::ONE_DAY, 1],
            'one week' => [TokenExpirationEnum::ONE_WEEK, 7],
            'one month' => [TokenExpirationEnum::ONE_MONTH, 30],
            'two months' => [TokenExpirationEnum::TWO_MONTHS, 60],
            'three months' => [TokenExpirationEnum::THREE_MONTHS, 90],
            'six months' => [TokenExpirationEnum::SIX_MONTHS, 180],
            'one year' => [TokenExpirationEnum::ONE_YEAR, 365],
        ];
    }

    public function test_expires_at_defaults_to_now_when_no_from_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));

        $result = TokenExpirationEnum::ONE_WEEK->expiresAt();

        $this->assertNotNull($result);
        $this->assertTrue(Carbon::parse('2026-06-22 12:00:00')->equalTo($result));

        Carbon::setTestNow();
    }
}
