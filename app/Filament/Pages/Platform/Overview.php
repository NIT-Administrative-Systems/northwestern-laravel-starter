<?php

declare(strict_types=1);

namespace App\Filament\Pages\Platform;

use App\Domains\Auth\Enums\SystemPermission;
use App\Filament\Navigation\AdministrationNavGroup;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Spatie\Health\ResultStores\EloquentHealthResultStore;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResult;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResults;
use UnitEnum;

/**
 * @phpstan-type InfoRow array{value: string, mono?: bool}
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
            'Lockdown Mode' => [
                'value' => config('platform.lockdown.enabled')
                    ? 'Enabled: Non-default roles required for access'
                    : 'Disabled: No role-based access restrictions',
            ],
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
}
