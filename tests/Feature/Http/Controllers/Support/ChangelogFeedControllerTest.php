<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Support;

use App\Domains\Support\Models\Changelog;
use App\Http\Controllers\Support\ChangelogFeedController;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ChangelogFeedController::class)]
final class ChangelogFeedControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/support/changelog/feed.rss', ChangelogFeedController::class)->name('support.changelog.feed');
    }

    public function test_feed_returns_rss_response_with_limited_entries(): void
    {
        config(['changelog.feed.limit' => 2]);

        Changelog::factory()->create(['slug' => '2025-01-01', 'authored_at' => now()->subDays(3)]);
        Changelog::factory()->create(['slug' => '2025-01-02', 'authored_at' => now()->subDays(2)]);
        Changelog::factory()->create(['slug' => '2025-01-03', 'authored_at' => now()->subDay()]);

        $response = $this->get(route('support.changelog.feed'));

        $response->assertOk();
        $response->assertViewIs('support.changelog.feed');
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

        $entries = $response->viewData('entries');
        $this->assertCount(2, $entries);
        $this->assertSame('2025-01-03', $entries->first()->slug);
    }
}
