<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Auth\Actions\Local;

use App\Domains\Auth\Actions\Local\FixedNumericOneTimeCodeGenerator;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(FixedNumericOneTimeCodeGenerator::class)]
class FixedNumericOneTimeCodeGeneratorTest extends TestCase
{
    private FixedNumericOneTimeCodeGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new FixedNumericOneTimeCodeGenerator();
    }

    public function test_generates_predictable_code_of_requested_length(): void
    {
        $code = ($this->generator)(6);

        $this->assertSame('123456', $code);
        $this->assertSame(6, strlen($code));
    }

    public function test_generates_single_digit_code(): void
    {
        $this->assertSame('1', ($this->generator)(1));
    }

    public function test_generates_code_longer_than_seed(): void
    {
        $code = ($this->generator)(15);

        $this->assertSame('123456789012345', $code);
        $this->assertSame(15, strlen($code));
    }

    public function test_throws_for_zero_digits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Digits must be >= 1.');

        ($this->generator)(0);
    }

    public function test_throws_for_negative_digits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Digits must be >= 1.');

        ($this->generator)(-1);
    }

    public function test_throws_in_production_environment(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must not be used in production');

        ($this->generator)(6);
    }
}
