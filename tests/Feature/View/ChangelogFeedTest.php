<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Domains\Support\Models\Changelog;
use PHPUnit\Framework\Attributes\CoversNothing;
use SimpleXMLElement;
use Tests\TestCase;

/**
 * The changelog's RSS feed, in resources/views/support/changelog.
 */
#[CoversNothing]
final class ChangelogFeedTest extends TestCase
{
    public function test_titles_reach_feed_readers_as_written(): void
    {
        config(['app.name' => 'Dean\'s Office & Registrar']);
        Changelog::factory()->create(['title' => 'What\'s New in <Spring>']);

        $feed = new SimpleXMLElement($this->get(route('support.changelog.feed'))->assertOk()->getContent());

        $this->assertSame('Dean\'s Office & Registrar Changelog (testing)', (string) $feed->channel->title);
        $this->assertSame('What\'s New in <Spring>', (string) $feed->channel->item[0]->title);
    }

    public function test_relative_links_and_images_reach_feed_readers_as_absolute_urls(): void
    {
        $entry = Changelog::factory()->create([
            'slug' => '2026-11-18',
            'body' => "### Added\n\nSee [Contact Support](/support/contact), [the next release](2026-12-01) "
                . "and [Northwestern](https://www.northwestern.edu).\n\n![New menu](/images/menu.png)",
        ]);

        $feed = new SimpleXMLElement($this->get(route('support.changelog.feed'))->assertOk()->getContent());
        $description = (string) $feed->channel->item[0]->description;
        $entryUrl = route('support.changelog.show', $entry);

        $this->assertStringContainsString('<h2>Added</h2>', $description);
        $this->assertStringContainsString('href="' . url('/support/contact') . '"', $description);
        $this->assertStringContainsString('href="' . dirname($entryUrl) . '/2026-12-01"', $description);
        $this->assertStringContainsString('href="https://www.northwestern.edu"', $description);
        $this->assertStringContainsString('src="' . url('/images/menu.png') . '"', $description);
    }
}
