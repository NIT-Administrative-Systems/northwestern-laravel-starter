<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Core\Casts;

use App\Domains\Core\Casts\MarkdownWithJiraLinksCast;
use App\Domains\Support\Models\Changelog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(MarkdownWithJiraLinksCast::class)]
final class MarkdownWithJiraLinksCastTest extends TestCase
{
    public function test_replaces_jira_ticket_reference_with_link(): void
    {
        config(['changelog.jira.identifier' => 'GSTS']);
        config(['changelog.jira.url' => 'https://jira.example.com']);

        $changelog = Changelog::factory()->create([
            'body' => 'Fixed a bug (`GSTS-1234`)',
        ]);

        $this->assertEquals(
            'Fixed a bug ([`GSTS-1234`](https://jira.example.com/browse/GSTS-1234))',
            $changelog->body,
        );
    }

    public function test_replaces_multiple_ticket_references_with_links(): void
    {
        config(['changelog.jira.identifier' => 'GSTS']);
        config(['changelog.jira.url' => 'https://jira.example.com']);

        $changelog = Changelog::factory()->create([
            'body' => 'Fixed bugs (`GSTS-1234`, `GSTS-5678`)',
        ]);

        $this->assertStringContainsString(
            '[`GSTS-1234`](https://jira.example.com/browse/GSTS-1234)',
            (string) $changelog->body,
        );
        $this->assertStringContainsString(
            '[`GSTS-5678`](https://jira.example.com/browse/GSTS-5678)',
            (string) $changelog->body,
        );
    }

    public function test_does_not_replace_when_setting_value(): void
    {
        config(['changelog.jira.identifier' => 'GSTS']);
        config(['changelog.jira.url' => 'https://jira.example.com']);

        $originalBody = 'Fixed a bug (`GSTS-1234`)';

        $changelog = Changelog::factory()->create([
            'body' => $originalBody,
        ]);

        $this->assertDatabaseHas('changelogs', [
            'id' => $changelog->id,
            'body' => $originalBody,
        ]);
    }

    public function test_noop_when_identifier_is_not_configured(): void
    {
        config(['changelog.jira.identifier' => null]);
        config(['changelog.jira.url' => 'https://jira.example.com']);

        $changelog = Changelog::factory()->create([
            'body' => 'Something (`GSTS-1234`)',
        ]);

        $this->assertEquals('Something (`GSTS-1234`)', $changelog->body);
    }

    public function test_noop_when_url_is_not_configured(): void
    {
        config(['changelog.jira.identifier' => 'GSTS']);
        config(['changelog.jira.url' => null]);

        $changelog = Changelog::factory()->create([
            'body' => 'Something (`GSTS-1234`)',
        ]);

        $this->assertEquals('Something (`GSTS-1234`)', $changelog->body);
    }

    public function test_does_not_replace_identifiers_without_backticks(): void
    {
        config(['changelog.jira.identifier' => 'GSTS']);
        config(['changelog.jira.url' => 'https://jira.example.com']);

        $changelog = Changelog::factory()->create([
            'body' => 'Fixed GSTS-1234 in production',
        ]);

        $this->assertEquals('Fixed GSTS-1234 in production', $changelog->body);
    }

    public function test_does_not_replace_different_project_identifiers(): void
    {
        config(['changelog.jira.identifier' => 'GSTS']);
        config(['changelog.jira.url' => 'https://jira.example.com']);

        $changelog = Changelog::factory()->create([
            'body' => 'See also (`FRS-999`)',
        ]);

        $this->assertEquals('See also (`FRS-999`)', $changelog->body);
    }

    public function test_handles_null_body_gracefully(): void
    {
        config(['changelog.jira.identifier' => 'GSTS']);
        config(['changelog.jira.url' => 'https://jira.example.com']);

        $cast = new MarkdownWithJiraLinksCast();
        $result = $cast->get(new Changelog(), 'body', null, []);

        $this->assertSame('', $result);
    }

    public function test_works_with_different_project_identifiers(): void
    {
        config(['changelog.jira.identifier' => 'COMPAPPS']);
        config(['changelog.jira.url' => 'https://jira.example.com']);

        $changelog = Changelog::factory()->create([
            'body' => 'Resolved (`COMPAPPS-42`)',
        ]);

        $this->assertStringContainsString(
            '[`COMPAPPS-42`](https://jira.example.com/browse/COMPAPPS-42)',
            (string) $changelog->body,
        );
    }

    public function test_output_uses_markdown_link_syntax_not_raw_html(): void
    {
        config(['changelog.jira.identifier' => 'GSTS']);
        config(['changelog.jira.url' => 'https://jira.example.com']);

        $changelog = Changelog::factory()->create([
            'body' => 'Fixed (`GSTS-100`)',
        ]);

        $this->assertStringNotContainsString('<a ', (string) $changelog->body);
        $this->assertStringNotContainsString('</a>', (string) $changelog->body);
        $this->assertStringNotContainsString('<code>', (string) $changelog->body);
        $this->assertStringContainsString('[`GSTS-100`](https://jira.example.com/browse/GSTS-100)', (string) $changelog->body);
    }

    #[DataProvider('xssVectorProvider')]
    public function test_xss_payloads_in_body_are_not_converted_to_jira_links(string $xssPayload): void
    {
        config(['changelog.jira.identifier' => 'GSTS']);
        config(['changelog.jira.url' => 'https://jira.example.com']);

        $changelog = Changelog::factory()->create([
            'body' => $xssPayload,
        ]);

        $this->assertStringNotContainsString('<a href="https://jira.example.com', (string) $changelog->body);
    }

    public function test_xss_payload_adjacent_to_jira_reference_does_not_pollute_link(): void
    {
        config(['changelog.jira.identifier' => 'GSTS']);
        config(['changelog.jira.url' => 'https://jira.example.com']);

        $changelog = Changelog::factory()->create([
            'body' => '<script>alert("xss")</script> Fixed (`GSTS-1234`)',
        ]);

        $this->assertStringContainsString('[`GSTS-1234`](https://jira.example.com/browse/GSTS-1234)', (string) $changelog->body);

        $this->assertStringContainsString('<script>', (string) $changelog->body);
    }

    /** @return \Iterator<string, array{string}> */
    public static function xssVectorProvider(): \Iterator
    {
        yield 'script tag' => ['<script>alert("xss")</script>'];
        yield 'script with src' => ['<script src="https://evil.com/xss.js"></script>'];
        yield 'img onerror' => ['<img src=x onerror=alert("xss")>'];
        yield 'svg onload' => ['<svg onload=alert("xss")>'];
        yield 'iframe injection' => ['<iframe src="https://evil.com"></iframe>'];
        yield 'javascript uri' => ['<a href="javascript:alert(\'xss\')">click</a>'];
        yield 'event handler in div' => ['<div onmouseover=alert("xss")>hover me</div>'];
        yield 'meta refresh' => ['<meta http-equiv="refresh" content="0;url=https://evil.com">'];
        yield 'object tag' => ['<object data="https://evil.com/exploit.swf"></object>'];
        yield 'embed tag' => ['<embed src="https://evil.com">'];
        yield 'base tag hijack' => ['<base href="https://evil.com">'];
        yield 'form action hijack' => ['<form action="https://evil.com"><input type="submit"></form>'];
    }

    public function test_trailing_slash_on_url_is_normalized(): void
    {
        config(['changelog.jira.identifier' => 'GSTS']);
        config(['changelog.jira.url' => 'https://jira.example.com/']);

        $changelog = Changelog::factory()->create([
            'body' => 'Fixed (`GSTS-100`)',
        ]);

        $this->assertStringContainsString(
            '](https://jira.example.com/browse/GSTS-100)',
            (string) $changelog->body,
        );
        $this->assertStringNotContainsString('//browse', (string) $changelog->body);
    }
}
