<?php

declare(strict_types=1);

namespace App\Filament\Pages\Platform;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\ApiRequestLog;
use App\Filament\Navigation\AdministrationNavGroup;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Health\ResultStores\EloquentHealthResultStore;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResult;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResults;
use Throwable;
use UnitEnum;

/**
 * @phpstan-type InfoRow array{value: string, mono?: bool}
 * @phpstan-type FeatureFlag array{label: string, enabled: bool, source: string}
 * @phpstan-type ScheduledTask array{command: string, description: string|null, expression: string, next_due_date: string|null, next_due_date_human: string|null, timezone: string|null}
 * @phpstan-type QueueStatus array{failed: int, pending: int, oldest_pending_at: ?Carbon, latest_failure: ?array{job: string, message: string, failed_at: Carbon}, top_pending: array<string, int>}
 * @phpstan-type LoginHeatmapCell array{count: int, bucket: int, hour: int, tooltip: string}
 * @phpstan-type LoginHeatmapRow array{date: Carbon, label: string, cells: list<LoginHeatmapCell>}
 * @phpstan-type LoginHeatmap array{rows: list<LoginHeatmapRow>, total: int, peak: array{count: int, when: Carbon}|null, days: int}
 * @phpstan-type ApiTraffic array{counts: list<int>, total: int, p95_ms: int}
 */
class Overview extends Page
{
    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected string $view = 'filament.pages.platform.overview';

    protected static ?string $title = 'Overview';

    protected ?string $subheading = 'Platform configuration and system health';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $slug = 'overview';

    protected static string|null|UnitEnum $navigationGroup = AdministrationNavGroup::Platform;

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()->hasPermissionTo(SystemPermission::ManageAll);
    }

    /**
     * @return array<string, InfoRow>
     */
    public function getEnvironmentInfo(): array
    {
        return [
            'PHP Version' => ['value' => phpversion(), 'mono' => true],
            'Laravel Version' => ['value' => app()->version(), 'mono' => true],
            'Production URL' => ['value' => config('platform.production_url'), 'mono' => true],
        ];
    }

    /**
     * @return array<string, InfoRow>
     */
    public function getServicesInfo(): array
    {
        $mailDriver = config('mail.default');
        $mailHost = config('mail.mailers.smtp.host');
        $mailPort = config('mail.mailers.smtp.port');

        $cacheDriver = config('cache.default');
        $queueConnection = config('queue.default');
        $sessionDriver = config('session.driver');
        $broadcastDriver = config('broadcasting.default');

        $dbDriver = config('database.default');
        $dbName = config('database.connections.' . $dbDriver . '.database');

        return [
            'Database' => ['value' => $dbDriver],
            'Database Name' => ['value' => $dbName, 'mono' => true],
            'Cache' => ['value' => ucfirst((string) $cacheDriver)],
            'Queue' => ['value' => ucfirst((string) $queueConnection)],
            'Session' => ['value' => ucfirst((string) $sessionDriver)],
            'Broadcasting' => ['value' => ucfirst((string) $broadcastDriver)],
            'Mail Driver' => ['value' => $mailDriver],
            'Mail Server' => [
                'value' => strtolower((string) $mailDriver) === 'ses'
                    ? 'AWS SES (Live)'
                    : Str::of((string) $mailHost)->append(':', $mailPort)->toString(),
                'mono' => true,
            ],
        ];
    }

    /**
     * @return array<string, InfoRow>
     */
    public function getObservabilityInfo(): array
    {
        $sentryDsn = config('sentry.dsn');
        $sentryEnabled = filled($sentryDsn);

        return [
            'Sentry' => ['value' => $sentryEnabled ? 'Enabled' : 'Disabled'],
            'Sample Rate' => ['value' => $sentryEnabled ? (string) config('sentry.sample_rate', 1.0) : 'N/A', 'mono' => $sentryEnabled],
            'Traces Sample Rate' => ['value' => $sentryEnabled ? (string) (config('sentry.traces_sample_rate', 'Disabled')) : 'N/A', 'mono' => $sentryEnabled],
            'Profiles Sample Rate' => ['value' => $sentryEnabled ? (string) (config('sentry.profiles_sample_rate', 'Disabled')) : 'N/A', 'mono' => $sentryEnabled],
        ];
    }

    /**
     * @return array<string, InfoRow>
     */
    public function getStorageInfo(): array
    {
        $disk = config('filesystems.default');
        $isS3 = $disk === 's3';

        $storageDetails = [
            'Default Disk' => ['value' => ucfirst((string) $disk)],
        ];

        if ($isS3) {
            $storageDetails['Bucket'] = ['value' => config('filesystems.disks.s3.bucket'), 'mono' => true];
            $storageDetails['Region'] = ['value' => config('filesystems.disks.s3.region') ?: 'Not set', 'mono' => true];
            $endpoint = config('filesystems.disks.s3.endpoint');
            $storageDetails['Endpoint'] = ['value' => $endpoint ?: 'AWS Default', 'mono' => (bool) $endpoint];
        } else {
            $storageDetails['Storage Type'] = ['value' => 'Local filesystem'];
        }

        return $storageDetails;
    }

    /**
     * Platform feature flags surfaced as a single overview, including their source.
     *
     * @return list<FeatureFlag>
     */
    public function getFeatureFlags(): array
    {
        return [
            [
                'label' => 'Lockdown Mode',
                'enabled' => (bool) config('platform.lockdown.enabled'),
                'source' => 'ENVIRONMENT_LOCKDOWN_ENABLED',
            ],
            [
                'label' => 'API',
                'enabled' => (bool) config('api.enabled'),
                'source' => 'API_ENABLED',
            ],
            [
                'label' => 'Changelog',
                'enabled' => (bool) config('changelog.enabled'),
                'source' => 'CHANGELOG_ENABLED',
            ],
            [
                'label' => 'Wildcard Photo Sync',
                'enabled' => (bool) config('platform.wildcard_photo_sync'),
                'source' => 'WILDCARD_PHOTO_SYNC_ENABLED',
            ],
            [
                'label' => 'EventHub Mock',
                'enabled' => (bool) config('nusoa.eventHub.mock'),
                'source' => 'EVENT_HUB_MOCK_ENABLED',
            ],
        ];
    }

    /**
     * External API integrations configuration.
     *
     * Each integration returns an array with:
     * - name: Display name of the integration
     * - icon: Heroicon class constant (e.g., Heroicon::OutlinedBolt)
     * - status: 'live', 'mock', or 'disabled'
     * - url: The API base URL being used
     *
     * To add a new integration, add an entry to the returned array.
     *
     * @return array<int, array{name: string, icon: Heroicon, status: string, url: string}>
     */
    public function getIntegrations(): array
    {
        $integrations = [];

        $eventHubMock = config('nusoa.eventHub.mock');
        $eventHubUrl = config('nusoa.eventHub.baseUrl');

        $integrations[] = [
            'name' => 'EventHub',
            'icon' => Heroicon::OutlinedBolt,
            'status' => $eventHubMock ? 'mock' : 'live',
            'url' => $eventHubMock ? url('/api/mock') : $eventHubUrl,
        ];

        $integrations[] = [
            'name' => 'Directory Search',
            'icon' => Heroicon::OutlinedMagnifyingGlass,
            'status' => 'live',
            'url' => config('nusoa.directorySearch.baseUrl'),
        ];

        return $integrations;
    }

    public function getHealthResults(): ?StoredCheckResults
    {
        return resolve(EloquentHealthResultStore::class)->latestResults();
    }

    /**
     * @return array{ok: int, warning: int, failed: int, skipped: int}
     */
    public function getHealthSummary(): array
    {
        $results = $this->getHealthResults();

        $summary = ['ok' => 0, 'warning' => 0, 'failed' => 0, 'skipped' => 0];

        if (! $results instanceof StoredCheckResults) {
            return $summary;
        }

        foreach ($results->storedCheckResults as $result) {
            $status = strtolower($result->status);
            if (isset($summary[$status])) {
                $summary[$status]++;
            } elseif ($status === 'crashed') {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    public function getHealthLastChecked(): ?Carbon
    {
        $results = $this->getHealthResults();

        if (! $results instanceof StoredCheckResults) {
            return null;
        }

        return Carbon::instance($results->finishedAt);
    }

    public function getStatusColor(string $status): string
    {
        return match (strtolower($status)) {
            'ok' => 'success',
            'warning' => 'warning',
            'failed', 'crashed' => 'danger',
            'skipped' => 'gray',
            default => 'gray',
        };
    }

    public function getStatusIcon(string $status): string
    {
        return match (strtolower($status)) {
            'ok' => 'heroicon-o-check-circle',
            'warning' => 'heroicon-o-exclamation-triangle',
            'failed', 'crashed' => 'heroicon-o-x-circle',
            'skipped' => 'heroicon-o-minus-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * @return array{name: string, label: string, status: string, summary: string, message: string, color: string, icon: string}
     */
    public function formatHealthResult(StoredCheckResult $result): array
    {
        return [
            'name' => $result->name,
            'label' => $result->label,
            'status' => $result->status,
            'summary' => $result->shortSummary,
            'message' => $result->notificationMessage,
            'color' => $this->getStatusColor($result->status),
            'icon' => $this->getStatusIcon($result->status),
        ];
    }

    /**
     * Queue status — returns null when nothing is stuck so callers can render conditionally.
     *
     * Uses the driver-agnostic Queue facade (Laravel 13.6+): `Queue::size()` for the count
     * and `Queue::pendingJobs()` for the `InspectedJob` collection that carries each job's
     * queued-at timestamp and display name. That replaces the previous direct reads on the
     * `jobs` table so this works on Database, Redis, and Beanstalkd queues without needing
     * a driver check. Failed jobs still come from the `failed_jobs` table — that surface
     * hasn't moved.
     *
     * @return QueueStatus|null
     */
    public function getQueueStatus(): ?array
    {
        try {
            $failed = 0;
            $latestFailure = null;

            if (Schema::hasTable('failed_jobs')) {
                $failed = DB::table('failed_jobs')->count();

                if ($failed > 0) {
                    $row = DB::table('failed_jobs')
                        ->latest('failed_at')
                        ->first(['payload', 'exception', 'failed_at']);

                    if ($row !== null) {
                        $latestFailure = [
                            'job' => $this->extractJobName((string) $row->payload),
                            'message' => $this->extractExceptionMessage((string) $row->exception),
                            'failed_at' => Carbon::parse((string) $row->failed_at),
                        ];
                    }
                }
            }

            $pending = Queue::size();
            $oldestPendingAt = null;

            /** @var array<string, int> $topPending */
            $topPending = [];

            if ($pending > 0) {
                $pendingJobs = Queue::pendingJobs();

                /** @var Carbon|null $oldestPendingAt */
                $oldestPendingAt = $pendingJobs
                    ->pluck('createdAt')
                    ->filter()
                    ->min();

                $topPending = $pendingJobs
                    ->filter(fn ($job) => $job->name !== null)
                    ->countBy('name')
                    ->sortDesc()
                    ->take(3)
                    ->all();
            }
        } catch (Throwable) {
            return null;
        }

        if ($failed === 0 && $pending === 0) {
            return null;
        }

        return [
            'failed' => $failed,
            'pending' => $pending,
            'oldest_pending_at' => $oldestPendingAt,
            'latest_failure' => $latestFailure,
            'top_pending' => $topPending,
        ];
    }

    /**
     * Pull a short, single-line excerpt out of a stored failed_jobs exception blob.
     *
     * Stack traces are stripped so the UI only shows the human-facing first line
     * (typically "<ExceptionClass>: <message>"), then truncated for display.
     */
    private function extractExceptionMessage(string $raw): string
    {
        $firstLine = (string) Str::of($raw)->before("\n")->trim();

        if ($firstLine === '') {
            return 'Unknown failure (no message recorded).';
        }

        return (string) Str::limit($firstLine, 180);
    }

    /**
     * Best-effort human name for the failed job, sourced from the queue payload.
     */
    private function extractJobName(string $payload): string
    {
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($payload, true);

        if (is_array($decoded) && isset($decoded['displayName']) && is_string($decoded['displayName'])) {
            return $decoded['displayName'];
        }

        return 'Unknown job';
    }

    /**
     * Login density matrix for the past N days × 24 hours, plus summary stats.
     *
     * Rows are ordered oldest → newest so the bottom row is "today" and reading
     * down the grid feels like time flowing forward. Each cell includes a
     * pre-bucketed intensity (0–4) so the view layer doesn't need to know the
     * global max — that keeps the Blade free of branching logic.
     *
     * Returns null only when the backing table is absent.
     *
     * @return LoginHeatmap|null
     */
    public function getLoginHeatmap(int $days = 7): ?array
    {
        if (! Schema::hasTable('user_login_records')) {
            return null;
        }

        $start = now()->startOfDay()->subDays($days - 1);

        /** @var list<object{day: string, hour: int, total: int}> $raw */
        $raw = DB::table('user_login_records')
            ->where('logged_in_at', '>=', $start)
            ->selectRaw("to_char(logged_in_at, 'YYYY-MM-DD') as day, extract(hour from logged_in_at)::int as hour, count(*) as total")
            ->groupBy('day', 'hour')
            ->get()
            ->all();

        // Keyed lookup so the row/cell loops below stay O(1) per cell.
        $index = [];
        foreach ($raw as $row) {
            $index[$row->day . ':' . $row->hour] = (int) $row->total;
        }

        $peakCount = 0;
        $peakWhen = null;
        $total = 0;

        foreach ($raw as $row) {
            $total += (int) $row->total;

            if ((int) $row->total > $peakCount) {
                $peakCount = (int) $row->total;
                $peakWhen = Carbon::parse($row->day)->setTime($row->hour, 0);
            }
        }

        $rows = [];
        for ($d = 0; $d < $days; $d++) {
            $date = $start->copy()->addDays($d);
            $dayKey = $date->toDateString();

            $cells = [];
            for ($h = 0; $h < 24; $h++) {
                $count = $index[$dayKey . ':' . $h] ?? 0;
                $cells[] = [
                    'count' => $count,
                    'bucket' => $this->intensityBucket($count, $peakCount),
                    'hour' => $h,
                    'tooltip' => $this->formatHeatmapTooltip($count, $date, $h),
                ];
            }

            $rows[] = [
                'date' => $date,
                'label' => $date->format('D'),
                'cells' => $cells,
            ];
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'peak' => $peakCount > 0 && $peakWhen instanceof Carbon
                ? ['count' => $peakCount, 'when' => $peakWhen]
                : null,
            'days' => $days,
        ];
    }

    /**
     * Quarter-scale bucket for a cell count. 0 = empty, 1–4 = ascending intensity.
     */
    private function intensityBucket(int $count, int $peak): int
    {
        if ($count <= 0 || $peak <= 0) {
            return 0;
        }

        $ratio = $count / $peak;

        return match (true) {
            $ratio > 0.75 => 4,
            $ratio > 0.50 => 3,
            $ratio > 0.25 => 2,
            default => 1,
        };
    }

    private function formatHeatmapTooltip(int $count, Carbon $date, int $hour): string
    {
        $when = $date->copy()->setTime($hour, 0)->format('D M j, H:i');

        if ($count === 0) {
            return 'No logins · ' . $when;
        }

        return $count . ' ' . Str::plural('login', $count) . ' · ' . $when;
    }

    /**
     * API request rate + latency for the last 60 minutes, bucketed per minute.
     *
     * Returns null when the API layer is disabled — the caller uses that to hide
     * the chart entirely rather than render a misleading "0 requests" line.
     *
     * @return ApiTraffic|null
     */
    public function getApiTrafficSeries(): ?array
    {
        if (! config('api.enabled')) {
            return null;
        }

        if (! Schema::hasTable('api_request_logs')) {
            return null;
        }

        $now = now();
        $start = $now->copy()->subMinutes(60);

        /** @var list<array{minute: int, count: int}> $countsByMinute */
        $countsByMinute = ApiRequestLog::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('floor(extract(epoch from created_at) / 60) as minute, count(*) as count')
            ->groupBy('minute')
            ->pluck('count', 'minute')
            ->all();

        $p95 = (int) ApiRequestLog::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('percentile_cont(0.95) within group (order by duration_ms) as p95')
            ->value('p95');

        $startMinute = (int) floor($start->timestamp / 60);
        $counts = [];
        $total = 0;
        for ($i = 0; $i < 60; $i++) {
            $bucket = $startMinute + $i;
            $value = (int) ($countsByMinute[$bucket] ?? 0);
            $counts[] = $value;
            $total += $value;
        }

        return [
            'counts' => $counts,
            'total' => $total,
            'p95_ms' => $p95,
        ];
    }

    /**
     * Scheduled tasks parsed from `php artisan schedule:list --json`.
     *
     * Running Artisan in-process is cheaper than spawning a shell, and the JSON output
     * gives us next-run timestamps the scheduler already computed.
     *
     * @return list<ScheduledTask>
     */
    public function getScheduledTasks(): array
    {
        try {
            Artisan::call('schedule:list', ['--json' => true]);
            /** @var list<array<string, mixed>>|null $decoded */
            $decoded = json_decode(Artisan::output(), true);
        } catch (Throwable) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $tasks = [];
        foreach ($decoded as $entry) {
            $tasks[] = [
                'command' => (string) ($entry['command'] ?? 'Closure'),
                'description' => isset($entry['description']) ? (string) $entry['description'] : null,
                'expression' => (string) ($entry['expression'] ?? ''),
                'next_due_date' => isset($entry['next_due_date']) ? (string) $entry['next_due_date'] : null,
                'next_due_date_human' => isset($entry['next_due_date_human']) ? (string) $entry['next_due_date_human'] : null,
                'timezone' => isset($entry['timezone']) ? (string) $entry['timezone'] : null,
            ];
        }

        usort($tasks, function (array $a, array $b): int {
            return ($a['next_due_date'] ?? '') <=> ($b['next_due_date'] ?? '');
        });

        return $tasks;
    }

    /**
     * Collapse an `artisan` command string into a concise label.
     *
     * `php artisan model:prune --path='app/Domains/Auth/Models'` becomes `model:prune`.
     */
    public function formatScheduledCommand(string $command): string
    {
        return (string) Str::of($command)
            ->replaceMatches('/^php\s+/i', '')
            ->replaceMatches('/^[\'"]?[^\s\'"]*artisan[\'"]?\s+/i', '')
            ->before(' ');
    }
}
