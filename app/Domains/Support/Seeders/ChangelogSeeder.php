<?php

declare(strict_types=1);

namespace App\Domains\Support\Seeders;

use App\Domains\Support\Models\Changelog;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\MarkdownConverter;
use Northwestern\SysDev\Chassis\Attributes\AutoSeed;
use Northwestern\SysDev\Chassis\Seeding\IdempotentSeeder;
use Symfony\Component\Finder\Finder;

/**
 * Syncs markdown changelog files from `resources/changelogs/` into the database.
 *
 * Each Markdown file must contain YAML front matter with at least `slug` and `date` fields.
 * The seeder creates new records, updates existing ones, and soft-deletes records whose
 * corresponding files have been removed.
 *
 * @see Changelog
 */
#[AutoSeed]
class ChangelogSeeder extends IdempotentSeeder
{
    protected string $model = Changelog::class;

    protected string $slugColumn = 'slug';

    private MarkdownConverter $markdownConverter;

    public function __construct()
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new FrontMatterExtension());

        $this->markdownConverter = new MarkdownConverter($environment);
    }

    public function run(): void
    {
        parent::run();

        // In local/testing, backdate created_at so entries display realistic
        // dates instead of all showing today's date after a fresh seed.
        if (App::environment('local', 'ci', 'testing')) {
            Changelog::query()->update(['created_at' => DB::raw('authored_at')]);
        }
    }

    /**
     * Scan changelog markdown files and return seed data.
     *
     * @return array<int, array{slug: string, title: string|null, authored_at: Carbon, body: string}>
     *
     * @throws CommonMarkException
     */
    public function data(): array
    {
        $path = resource_path('changelogs');

        if (! is_dir($path)) {
            return [];
        }

        $files = Finder::create()->files()->in($path)->name('*.md')->sortByName();
        $entries = [];

        foreach ($files as $file) {
            $raw = $file->getContents();
            $rendered = $this->markdownConverter->convert($raw);

            if (! $rendered instanceof RenderedContentWithFrontMatter) {
                throw new InvalidArgumentException(
                    "Front matter is required in {$file->getFilename()}."
                );
            }

            /** @var array<string, mixed> $frontMatter */
            $frontMatter = $rendered->getFrontMatter();

            $slug = Arr::get($frontMatter, 'slug')
                ?? throw new InvalidArgumentException("Missing 'slug' in {$file->getFilename()}.");

            $date = Arr::get($frontMatter, 'date')
                ?? throw new InvalidArgumentException("Missing 'date' in {$file->getFilename()}.");

            $entries[] = [
                'slug' => $slug,
                'title' => Arr::get($frontMatter, 'title'),
                'authored_at' => Carbon::parse($date),
                'body' => $raw,
            ];
        }

        return $entries;
    }
}
