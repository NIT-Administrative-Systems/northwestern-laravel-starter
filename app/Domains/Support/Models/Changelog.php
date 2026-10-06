<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use App\Domains\Core\Casts\MarkdownWithJiraLinksCast;
use App\Domains\Core\Models\BaseModel;
use App\Domains\Support\Seeders\ChangelogSeeder;
use Database\Factories\Domains\Support\Models\ChangelogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\HtmlString;
use Northwestern\SysDev\Chassis\Markdown\ShiftHeadings;
use Spatie\LaravelMarkdown\MarkdownRenderer;

/**
 * A single changelog/release note entry synced from a Markdown file.
 *
 * Entries are code-driven: Markdown files in `resources/changelogs/` are the single
 * source of truth. The {@see ChangelogSeeder} syncs them into the database on
 * every deployment.
 */
class Changelog extends BaseModel
{
    /** @use HasFactory<ChangelogFactory> */
    use HasFactory, SoftDeletes;

    /** @var array<string, string> */
    protected $casts = [
        'authored_at' => 'date',
        'body' => MarkdownWithJiraLinksCast::class,
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('releaseOrder', function (Builder $builder): void {
            $builder->latest('authored_at');
        });
    }

    /**
     * The entry's Markdown as HTML, with its headings moved to start at `$topHeadingLevel`, so they
     * follow the page's own: level 2 under an entry page's <h1>, level 3 under the index's <h2> titles.
     *
     * @param  int<1, 6>  $topHeadingLevel
     */
    public function bodyHtml(int $topHeadingLevel): HtmlString
    {
        // Uncached: the renderer's cache key leaves out extensions, so each heading level would get the other's HTML.
        return new HtmlString((clone resolve(MarkdownRenderer::class))
            ->cacheStoreName(false)
            ->disableAnchors()
            ->commonmarkOptions(['html_input' => 'escape'])
            ->addExtension(new ShiftHeadings($topHeadingLevel))
            ->toHtml((string) $this->body));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
