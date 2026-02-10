<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Foundation\Casts;

use App\Domains\Foundation\Casts\MarkdownWithJiraLinksCast;
use App\Domains\Support\Models\Changelog;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(MarkdownWithJiraLinksCast::class)]
class MarkdownWithJiraLinksCastTest extends TestCase
{
    public function test_replaces_jira_ticket_reference_with_link(): void
    {
        config(['changelog.jira.identifier' => 'GSTS']);
        config(['changelog.jira.url' => 'https://jira.example.com']);

        $changelog = Changelog::factory()->create([
            'body' => 'Fixed a bug (`GSTS-1234`)',
        ]);

        $this->assertEquals(
            'Fixed a bug (<a href="https://jira.example.com/browse/GSTS-1234" target="_blank" rel="noopener"><code>GSTS-1234</code></a>)',
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
            '<a href="https://jira.example.com/browse/GSTS-1234" target="_blank" rel="noopener"><code>GSTS-1234</code></a>',
            $changelog->body,
        );
        $this->assertStringContainsString(
            '<a href="https://jira.example.com/browse/GSTS-5678" target="_blank" rel="noopener"><code>GSTS-5678</code></a>',
            $changelog->body,
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

        $this->assertEquals('', $result);
    }

    public function test_works_with_different_project_identifiers(): void
    {
        config(['changelog.jira.identifier' => 'COMPAPPS']);
        config(['changelog.jira.url' => 'https://jira.example.com']);

        $changelog = Changelog::factory()->create([
            'body' => 'Resolved (`COMPAPPS-42`)',
        ]);

        $this->assertStringContainsString(
            'href="https://jira.example.com/browse/COMPAPPS-42"',
            $changelog->body,
        );
    }

    public function test_trailing_slash_on_url_is_normalized(): void
    {
        config(['changelog.jira.identifier' => 'GSTS']);
        config(['changelog.jira.url' => 'https://jira.example.com/']);

        $changelog = Changelog::factory()->create([
            'body' => 'Fixed (`GSTS-100`)',
        ]);

        $this->assertStringContainsString(
            'href="https://jira.example.com/browse/GSTS-100"',
            $changelog->body,
        );
        $this->assertStringNotContainsString('//browse', $changelog->body);
    }
}
