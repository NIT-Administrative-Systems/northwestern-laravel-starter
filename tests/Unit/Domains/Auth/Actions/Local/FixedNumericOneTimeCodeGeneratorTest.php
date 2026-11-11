<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Auth\Actions\Local;

use App\Domains\Auth\Actions\Local\FixedNumericOneTimeCodeGenerator;
use App\Domains\Auth\Contracts\OneTimeCodeGenerator;
use App\Providers\AppServiceProvider;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(FixedNumericOneTimeCodeGenerator::class)]
final class FixedNumericOneTimeCodeGeneratorTest extends TestCase
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
        $this->expectExceptionMessageIsOrContains('Digits must be >= 1.');

        ($this->generator)(0); // @phpstan-ignore argument.type
    }

    public function test_throws_for_negative_digits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Digits must be >= 1.');

        ($this->generator)(-1); // @phpstan-ignore argument.type
    }

    #[DataProvider('blockedEnvironmentProvider')]
    public function test_throws_outside_local_testing_and_ci(string $environment): void
    {
        $this->app['env'] = $environment;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The fixed sign-in code can only be used in the local, testing and ci environments');

        ($this->generator)(6);
    }

    #[DataProvider('allowedEnvironmentProvider')]
    public function test_generates_in_local_testing_and_ci(string $environment): void
    {
        $this->app['env'] = $environment;

        $this->assertSame('123456', ($this->generator)(6));
    }

    // Checked when the application boots, so a deployment that turns it on fails at once.
    public function test_the_application_refuses_to_boot_with_it_turned_on_in_a_deployed_environment(): void
    {
        config(['local-auth.use_fixed_code' => true]);
        $this->app['env'] = 'staging';

        $this->expectException(LogicException::class);

        new AppServiceProvider($this->app)->register();
    }

    public function test_the_application_boots_with_it_turned_on_locally(): void
    {
        config(['local-auth.use_fixed_code' => true]);
        $this->app['env'] = 'local';

        new AppServiceProvider($this->app)->register();

        $this->assertInstanceOf(FixedNumericOneTimeCodeGenerator::class, $this->app->make(OneTimeCodeGenerator::class));
    }

    /** @return \Iterator<string, array{string}> */
    public static function blockedEnvironmentProvider(): \Iterator
    {
        yield 'production' => ['production'];
        yield 'develop' => ['develop'];
        yield 'qa' => ['qa'];
        yield 'staging' => ['staging'];
        yield 'an environment the starter doesn\'t name' => ['uat'];
    }

    /** @return \Iterator<string, array{string}> */
    public static function allowedEnvironmentProvider(): \Iterator
    {
        yield 'local' => ['local'];
        yield 'testing' => ['testing'];
        yield 'ci' => ['ci'];
    }
}
