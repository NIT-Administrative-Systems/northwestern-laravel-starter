<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Checks for newer releases of the Northwestern Laravel Starter.
 *
 * Designed to run automatically via `composer install` in local
 * environments.
 *
 * @see https://docs.github.com/en/rest/releases/releases#list-releases
 */
class StarterCheckCommand extends Command
{
    protected $signature = 'starter:check
        {--version-file= : Path to .starter-version.yaml (defaults to project root)}';

    protected $description = 'Check for Northwestern Laravel Starter updates';

    private const string REPO = 'NIT-Administrative-Systems/northwestern-laravel-starter';

    private const string CACHE_KEY = 'starter-check-releases';

    private const int CACHE_TTL_HOURS = 4;

    public function handle(): int
    {
        try {
            return $this->check();
        } catch (\PDOException) {
            // Gracefully degrade when the database is unavailable (e.g. fresh
            // installs via `composer create-project` before the DB exists).
            return self::SUCCESS;
        }
    }

    private function check(): int
    {
        if ($this->shouldSkip()) {
            return self::SUCCESS;
        }

        $currentVersion = $this->readCurrentVersion();

        if ($currentVersion === null) {
            return self::SUCCESS;
        }

        $releases = $this->fetchReleases();

        if (! $releases instanceof Collection) {
            return self::SUCCESS;
        }

        /** @var Collection<int, array<string, mixed>> $newer */
        $newer = $releases
            ->reject(fn (array $release): bool => $release['draft'] || $release['prerelease'])
            ->filter(fn (array $release): bool => version_compare(
                ltrim($release['tag_name'], 'v'),
                ltrim($currentVersion, 'v'),
                '>',
            ))
            ->sortByDesc('published_at')
            ->values();

        if ($newer->isEmpty()) {
            return self::SUCCESS;
        }

        $this->displayNotice($currentVersion, $newer);

        return self::SUCCESS;
    }

    private function shouldSkip(): bool
    {
        if (App::environment('production', 'staging', 'qa', 'develop')) {
            return true;
        }

        return ! empty($_ENV['CI']) || ! empty($_SERVER['CI']);
    }

    private function readCurrentVersion(): ?string
    {
        $path = $this->option('version-file') ?: base_path('.starter-version.yaml');

        if (! file_exists($path)) {
            return null;
        }

        try {
            $parsed = Yaml::parseFile($path);
        } catch (ParseException) {
            return null;
        }

        if (! is_array($parsed) || ! is_string($parsed['version'] ?? null)) {
            return null;
        }

        $version = trim($parsed['version']);

        if (! preg_match('/^v?\d+\.\d+\.\d+$/', $version)) {
            return null;
        }

        return $version;
    }

    /**
     * @return Collection<int, array<string, mixed>>|null
     */
    private function fetchReleases(): ?Collection
    {
        /** @var Collection<int, array<string, mixed>>|null $cached */
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached instanceof Collection) {
            return $cached;
        }

        $apiUrl = 'https://api.github.com/repos/' . self::REPO . '/releases';

        try {
            $response = Http::timeout(10)
                ->allowStrayRequests([$apiUrl])
                ->get($apiUrl);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        /** @var list<array<string, mixed>> $json */
        $json = $response->json();

        $releases = collect($json);

        Cache::put(self::CACHE_KEY, $releases, now()->addHours(self::CACHE_TTL_HOURS));

        return $releases;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $newer
     */
    private function displayNotice(string $currentVersion, Collection $newer): void
    {
        $latestVersion = $newer->first()['tag_name'];
        $count = $newer->count();
        $releaseWord = $count === 1 ? 'release' : 'releases';

        $this->newLine();
        $this->components->warn(
            "Northwestern Laravel Starter — {$count} {$releaseWord} behind ({$currentVersion} → {$latestVersion})",
        );

        foreach ($newer as $release) {
            $tag = $release['tag_name'];
            $date = date('Y-m-d', strtotime((string) $release['published_at']));
            $htmlUrl = (string) ($release['html_url'] ?? '');

            $this->components->twoColumnDetail(
                "  <fg=white;options=bold>{$tag}</>",
                "<fg=gray>{$date}</>  <fg=cyan>{$htmlUrl}</>",
            );
        }

        $this->newLine();

        $compareUrl = 'https://github.com/' . self::REPO . "/compare/{$currentVersion}...{$latestVersion}";

        $this->components->twoColumnDetail(
            '  <fg=white;options=bold>Full diff</>',
            "<fg=cyan>{$compareUrl}</>",
        );

        $changelogUrl = 'https://github.com/' . self::REPO . '/blob/main/CHANGELOG.md';

        $this->components->twoColumnDetail(
            '  <fg=white;options=bold>Changelog</>',
            "<fg=cyan>{$changelogUrl}</>",
        );

        $this->newLine();
        $this->line('  <fg=gray>Review the releases above to see if any changes are relevant to your project.</>');
        $this->line("  <fg=gray>To suppress these notices, set</> <fg=white;options=bold>version: {$latestVersion}</> <fg=gray>in</> <fg=white;options=bold>.starter-version.yaml</>");
        $this->newLine();
    }
}
