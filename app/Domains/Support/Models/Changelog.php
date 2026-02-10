<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use App\Domains\Core\Models\BaseModel;
use App\Domains\Foundation\Casts\MarkdownWithJiraLinksCast;
use App\Domains\Support\Seeders\ChangelogSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single changelog/release note entry synced from a Markdown file.
 *
 * Entries are code-driven: Markdown files in `resources/changelogs/` are the single
 * source of truth. The {@see ChangelogSeeder} syncs them into the database on
 * every deployment.
 */
class Changelog extends BaseModel
{
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

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
