<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\View\Components\Clipboard;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(Clipboard::class)]
class ClipboardTest extends TestCase
{
    public function test_creates_with_defaults(): void
    {
        $component = new Clipboard(text: 'Copy me');

        $this->assertSame('Copy me', $component->text);
        $this->assertNull($component->label);
        $this->assertTrue($component->isButton);
        $this->assertNull($component->buttonSize);
        $this->assertSame('outline-secondary', $component->buttonVariant);
        $this->assertSame('outline-success', $component->successVariant);
        $this->assertSame('left', $component->iconPosition);
    }

    public function test_creates_with_custom_values(): void
    {
        $component = new Clipboard(
            text: 'Copy this',
            label: 'Copy',
            isButton: false,
            buttonSize: 'sm',
            buttonVariant: 'primary',
            successVariant: 'success',
            iconPosition: 'right',
        );

        $this->assertSame('Copy this', $component->text);
        $this->assertSame('Copy', $component->label);
        $this->assertFalse($component->isButton);
        $this->assertSame('sm', $component->buttonSize);
        $this->assertSame('primary', $component->buttonVariant);
        $this->assertSame('success', $component->successVariant);
        $this->assertSame('right', $component->iconPosition);
    }

    public function test_throws_for_blank_text(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Text cannot be blank.');

        new Clipboard(text: '');
    }

    #[DataProvider('invalidVariantProvider')]
    public function test_throws_for_invalid_button_variant(string $variant): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid button variant '{$variant}'");

        new Clipboard(text: 'Test', buttonVariant: $variant);
    }

    #[DataProvider('invalidVariantProvider')]
    public function test_throws_for_invalid_success_variant(string $variant): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid button variant '{$variant}'");

        new Clipboard(text: 'Test', successVariant: $variant);
    }

    /** @return array<string, array{0: string}> */
    public static function invalidVariantProvider(): array
    {
        return [
            'completely invalid' => ['nope'],
            'typo' => ['primry'],
        ];
    }

    public function test_throws_for_invalid_button_size(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid button size 'xl'");

        new Clipboard(text: 'Test', buttonSize: 'xl');
    }

    public function test_throws_for_invalid_icon_position(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid icon position 'center'");

        new Clipboard(text: 'Test', iconPosition: 'center');
    }

    public function test_accepts_none_icon_position(): void
    {
        $component = new Clipboard(text: 'Test', iconPosition: 'none');

        $this->assertSame('none', $component->iconPosition);
    }

    public function test_accepts_lg_button_size(): void
    {
        $component = new Clipboard(text: 'Test', buttonSize: 'lg');

        $this->assertSame('lg', $component->buttonSize);
    }

    public function test_renders_view(): void
    {
        $component = new Clipboard(text: 'Test');

        $this->assertSame('components.clipboard', $component->render()->name());
    }
}
