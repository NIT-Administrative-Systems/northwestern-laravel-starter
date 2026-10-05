<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Rules;

use App\Domains\Auth\Rules\OAuthRedirectUri;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(OAuthRedirectUri::class)]
final class OAuthRedirectUriTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function uris(): iterable
    {
        yield 'https' => ['https://app.example.edu/callback', true];
        yield 'loopback http' => ['http://localhost:4100/callback', true];
        yield 'loopback ip' => ['http://127.0.0.1/callback', true];
        yield 'plain http elsewhere' => ['http://app.example.edu/callback', false];
        yield 'fragment' => ['https://app.example.edu/callback#x', false];
        yield 'relative' => ['/callback', false];
        yield 'custom scheme' => ['myapp://callback', false];
        yield 'credentials' => ['https://user:secret@app.example.edu/callback', false];
    }

    #[DataProvider('uris')]
    public function test_it_accepts_https_and_loopback_only(string $uri, bool $valid): void
    {
        $this->assertSame($valid, Validator::make(['uri' => $uri], ['uri' => [new OAuthRedirectUri()]])->passes());
    }

    // Desktop MCP clients return through their own scheme, when it's configured.
    public function test_it_accepts_only_the_custom_schemes_it_is_given(): void
    {
        $rule = new OAuthRedirectUri(['cursor']);

        $this->assertTrue(Validator::make(['uri' => 'cursor://anysphere.cursor-mcp/oauth/callback'], ['uri' => [$rule]])->passes());
        $this->assertFalse(Validator::make(['uri' => 'vscode://vscode.github-authentication/did-authenticate'], ['uri' => [$rule]])->passes());
    }
}
