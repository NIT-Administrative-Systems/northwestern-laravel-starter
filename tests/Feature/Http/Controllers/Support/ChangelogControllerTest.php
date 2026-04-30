<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Support;

use App\Domains\Support\Models\Changelog;
use App\Http\Controllers\Support\ChangelogController;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ChangelogController::class)]
final class ChangelogControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/support/changelog', [ChangelogController::class, 'index'])->name('support.changelog.index');
        Route::get('/support/changelog/feed.rss', fn () => 'feed')->name('support.changelog.feed');
        Route::get('/support/changelog/{changelog}', [ChangelogController::class, 'show'])->name('support.changelog.show');
    }

    public function test_index_returns_view_with_paginated_entries_and_feed_url(): void
    {
        config(['changelog.pagination.per_page' => 2]);

        Changelog::factory()->create(['authored_at' => now()->subDays(3), 'slug' => '2025-01-01']);
        Changelog::factory()->create(['authored_at' => now()->subDays(2), 'slug' => '2025-01-02']);
        Changelog::factory()->create(['authored_at' => now()->subDay(), 'slug' => '2025-01-03']);

        $response = $this->get(route('support.changelog.index'));

        $response->assertOk();
        $response->assertViewIs('support.changelog.index');
        $response->assertViewHas('feedUrl', route('support.changelog.feed'));

        $entries = $response->viewData('entries');
        $this->assertSame(2, $entries->count());
        $this->assertSame('2025-01-03', $entries->first()->slug);
    }

    public function test_show_returns_view_for_selected_changelog_entry(): void
    {
        $entry = Changelog::factory()->create([
            'slug' => 'changelog-show-entry-test',
            'title' => 'February update',
            'authored_at' => now(),
        ]);

        $controller = new ChangelogController();
        $view = $controller->show($entry);
        $data = $view->getData();

        $this->assertSame('support.changelog.show', $view->name());
        $this->assertTrue($data['entry']->is($entry));
    }
}
