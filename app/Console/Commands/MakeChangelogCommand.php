<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;

/**
 * Scaffolds a new changelog Markdown file with YAML front matter.
 *
 * Creates a new entry in `resources/changelogs/` using the stub template.
 * Supports interactive prompts for slug, date, and title, with sensible
 * defaults and automatic deduplication for conflicting filenames.
 */
#[AsCommand(name: 'make:changelog')]
class MakeChangelogCommand extends Command
{
    /** @var string */
    protected $signature = 'make:changelog
        {slug? : URL-safe identifier for the entry (defaults to today\'s date)}
        {--date= : The authored date in YYYY-MM-DD format (defaults to today)}
        {--title= : Human-readable title for the entry}';

    /** @var string */
    protected $description = 'Create a new changelog markdown file in resources/changelogs/';

    public function handle(): int
    {
        intro('New Changelog Entry');

        $today = Carbon::today()->toDateString();

        $slug = $this->resolveSlug($today);
        $date = $this->resolveDate($today);
        $title = $this->resolveTitle($slug);

        $basePath = resource_path('changelogs');
        $filename = "{$slug}.md";
        $filepath = "{$basePath}/{$filename}";

        if (file_exists($filepath)) {
            $filepath = $this->deduplicatePath($basePath, $slug);
            $filename = basename($filepath);
            $slug = Str::beforeLast($filename, '.md');
        }

        if (! is_dir($basePath) && ! mkdir($basePath, 0755, true) && ! is_dir($basePath)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $basePath));
        }

        $content = str_replace(
            ['{{ slug }}', '{{ date }}', '{{ title }}'],
            [$slug, $date, $title],
            file_get_contents(base_path('stubs/changelog.stub')),
        );

        file_put_contents($filepath, $content);

        note("resources/changelogs/{$filename}");
        $this->components->success('Changelog entry created. Edit the file to add your release notes.');

        return self::SUCCESS;
    }

    /**
     * Determine the slug from the argument, option, or interactive prompt.
     */
    private function resolveSlug(string $today): string
    {
        $slug = $this->argument('slug');

        if (is_string($slug)) {
            return Str::slug($slug);
        }

        if (! $this->input->isInteractive()) {
            return $today;
        }

        $input = text(
            label: 'Slug',
            placeholder: $today,
            default: $today,
            hint: 'URL-safe identifier. Used as the filename and URL path segment.',
        );

        return Str::slug($input);
    }

    /**
     * Determine the date from the option or interactive prompt.
     */
    private function resolveDate(string $today): string
    {
        $date = $this->option('date');

        if (is_string($date)) {
            return $date;
        }

        if (! $this->input->isInteractive()) {
            return $today;
        }

        return text(
            label: 'Authored date',
            placeholder: $today,
            default: $today,
            validate: fn (string $value): ?string => Carbon::createFromFormat('Y-m-d', $value) instanceof Carbon
                ? null
                : 'Please enter a valid date in YYYY-MM-DD format.',
            hint: 'The canonical release date shown to users (YYYY-MM-DD).',
        );
    }

    /**
     * Determine the title from the option or interactive prompt.
     */
    private function resolveTitle(string $slug): string
    {
        $title = $this->option('title');

        if (is_string($title)) {
            return $title;
        }

        if (! $this->input->isInteractive()) {
            return $this->generateDefaultTitle($slug);
        }

        $default = $this->generateDefaultTitle($slug);

        return text(
            label: 'Title',
            placeholder: $default,
            default: $default,
            hint: 'Human-readable title displayed in the changelog header.',
        );
    }

    /**
     * Generate a human-readable title from a date-based slug.
     *
     * If the slug looks like a date (YYYY-MM-DD), it's formatted as "Month YYYY Release".
     * Otherwise, the slug is title-cased.
     */
    private function generateDefaultTitle(string $slug): string
    {
        $date = Carbon::createFromFormat('Y-m-d', $slug);

        if (! $date instanceof Carbon) {
            return Str::title(str_replace('-', ' ', $slug));
        }

        return $date->format('F Y') . ' Release';
    }

    /**
     * Find a non-conflicting filepath by appending a numeric suffix.
     */
    private function deduplicatePath(string $basePath, string $slug): string
    {
        $suffix = 1;

        do {
            $path = "{$basePath}/{$slug}-{$suffix}.md";
            $suffix++;
        } while (file_exists($path));

        $newSlug = Str::beforeLast(basename($path), '.md');

        if ((bool) $this->input->isInteractive()) {
            confirm(
                label: "{$slug}.md already exists. Create {$newSlug}.md instead?",
                default: true,
                hint: 'A numeric suffix has been added to avoid overwriting the existing file.',
            );
        }

        return $path;
    }
}
