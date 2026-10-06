<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Support\Formatting;

use App\Filament\Support\Formatting\TitleCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TitleCase::class)]
final class TitleCaseTest extends TestCase
{
    /**
     * @return \Iterator<string, array{string, string}>
     */
    public static function names(): \Iterator
    {
        yield 'plain words' => ['personal access token', 'Personal Access Token'];
        yield 'small words stay lowercase' => ['applications with access to your account', 'Applications with Access to Your Account'];
        yield 'a small word first or last is capitalized' => ['the token to look for', 'The Token to Look For'];
        yield 'prepositions of any length' => ['sign-ins from the last day without errors', 'Sign-Ins from the Last Day without Errors'];
        yield 'a verb\'s particle is capitalized' => ['sign in with email', 'Sign In with Email'];
        yield 'already capitalized small words are lowered' => ['Date And Time', 'Date and Time'];
        yield 'acronyms and inner capitals are kept' => ['revoke MCP client for myHR via OAuth', 'Revoke MCP Client for myHR via OAuth'];
        yield 'NetID' => ['sign out of NetID', 'Sign Out of NetID'];
        yield 'hyphenated words' => ['sign-in records', 'Sign-In Records'];
        yield 'placeholders are kept' => ['delete selected :label', 'Delete Selected :label'];
    }

    #[DataProvider('names')]
    public function test_it_applies_chicago_headline_style(string $text, string $expected): void
    {
        $this->assertSame($expected, TitleCase::of($text));
    }
}
