<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Auth\Actions\Local;

use App\Domains\Auth\Actions\Local\RandomNumericOneTimeCodeGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RandomNumericOneTimeCodeGenerator::class)]
class RandomNumericOneTimeCodeGeneratorTest extends TestCase
{
    private RandomNumericOneTimeCodeGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new RandomNumericOneTimeCodeGenerator();
    }

    public function test_generates_code_of_requested_length(): void
    {
        $code = ($this->generator)(6);

        $this->assertSame(6, strlen($code));
        $this->assertTrue(ctype_digit($code));
    }

    public function test_generates_single_digit_code(): void
    {
        $code = ($this->generator)(1);

        $this->assertSame(1, strlen($code));
        $this->assertTrue(ctype_digit($code));
    }

    public function test_generates_code_within_expected_range(): void
    {
        $code = ($this->generator)(4);

        $this->assertGreaterThanOrEqual(1000, (int) $code);
        $this->assertLessThanOrEqual(9999, (int) $code);
    }

    public function test_throws_for_zero_digits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Digits must be >= 1.');

        ($this->generator)(0); // @phpstan-ignore argument.type
    }

    public function test_throws_for_negative_digits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Digits must be >= 1.');

        ($this->generator)(-1); // @phpstan-ignore argument.type
    }
}
