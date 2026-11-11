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
}
