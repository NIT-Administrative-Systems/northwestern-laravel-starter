<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Core\Formatting;

use App\Domains\Core\Formatting\CountInWords;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CountInWords::class)]
final class CountInWordsTest extends TestCase
{
    public function test_it_spells_out_one_through_nine_and_pluralizes(): void
    {
        $this->assertSame('one minute', CountInWords::of(1, 'minute'));
        $this->assertSame('five seconds', CountInWords::of(5, 'second'));
        $this->assertSame('30 seconds', CountInWords::of(30, 'second'));
    }
}
