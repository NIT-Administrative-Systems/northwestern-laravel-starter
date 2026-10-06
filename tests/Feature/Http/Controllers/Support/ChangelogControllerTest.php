<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Support;

use App\Domains\Support\Models\Changelog;
use App\Domains\User\Models\User;
use App\Http\Controllers\Support\ChangelogController;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ChangelogController::class)]
final class ChangelogControllerTest extends TestCase
{
    public function test_index_returns_view_with_paginated_entries_and_feed_url(): void
    {
        config(['changelog.pagination.per_page' => 2]);

        Changelog::factory()->create(['authored_at' => now()->subDays(3), 'slug' => '2025-01-01']);
        Changelog::factory()->create(['authored_at' => now()->subDays(2), 'slug' => '2025-01-02']);
        Changelog::factory()->create(['authored_at' => now()->subDay(), 'slug' => '2025-01-03']);

        $response = $this->get(route('support.changelog.index'));

        $response->assertOk();
        $response->assertViewIs('public.changelog.index');
        $response->assertSee('RSS Feed');
        $response->assertSee('Showing');
        // The published pagination view labels Previous and Next as plain text, with a role on the disabled one.
        $response->assertSee('<span role="link" aria-disabled="true" aria-label="Previous">', escape: false);
        $response->assertSee('aria-label="Next"', escape: false);
        $response->assertDontSee('aria-label="&amp;laquo;', escape: false);
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

        $this->get(route('support.changelog.show', $entry))
            ->assertOk()
            ->assertViewIs('public.changelog.show')
            ->assertViewHas('entry', fn (Changelog $shown): bool => $shown->is($entry))
            ->assertSee('February update');
    }

    // Entries are written with ### headings; they follow the page's own headings without skipping a level.
    public function test_entry_headings_follow_the_page_headings(): void
    {
        $entry = Changelog::factory()->create(['body' => "### Changes\n\n#### Detail\n\n- A change"]);

        $this->get(route('support.changelog.show', $entry))
            ->assertSee('<h2>Changes</h2>', escape: false)
            ->assertSee('<h3>Detail</h3>', escape: false);

        $this->get(route('support.changelog.index'))
            ->assertSee('<h3>Changes</h3>', escape: false)
            ->assertSee('<h4>Detail</h4>', escape: false);
    }

    public function test_an_entry_without_headings_renders_as_written(): void
    {
        $entry = Changelog::factory()->create(['body' => "- A change\n- Another change"]);

        $this->get(route('support.changelog.show', $entry))
            ->assertOk()
            ->assertSee('<li>Another change</li>', escape: false);
    }

    public function test_changelog_is_public_and_renders_on_the_public_layout(): void
    {
        $this->get(route('support.changelog.index'))
            ->assertOk()
            ->assertSee('data-cy="sign-in-link"', escape: false)
            ->assertSee('Privacy Statement');
    }

    public function test_feed_url_is_not_treated_as_an_entry_slug(): void
    {
        $this->get(route('support.changelog.feed'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    public function test_signed_in_users_get_the_user_menu_instead_of_sign_in(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('support.changelog.index'))
            ->assertOk()
            ->assertSee('fi-user-menu', escape: false)
            ->assertDontSee('data-cy="sign-in-link"', escape: false);
    }

    public function test_show_offers_a_share_button(): void
    {
        $entry = Changelog::factory()->create(['slug' => 'share-button-test', 'authored_at' => now()]);

        $this->get(route('support.changelog.show', $entry))
            ->assertOk()
            ->assertSee('navigator.share', escape: false)
            ->assertSee('Share');
    }
}
