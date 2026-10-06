<?php

declare(strict_types=1);

namespace App\Filament\Clusters\ApiCluster\Pages;

use App\Domains\Auth\Models\ApiRequestLog;
use App\Domains\Auth\Models\OAuthClient;
use App\Filament\Clusters\ApiCluster;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;

class Overview extends Page
{
    protected static ?string $cluster = ApiCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $title = 'Overview';

    protected static ?string $slug = 'overview';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.clusters.api-cluster.pages.overview';

    protected ?string $subheading = 'API configuration and usage statistics';

    /**
     * Filament checks a cluster's rule only for its navigation; each clustered page needs its own.
     */
    public static function canAccess(): bool
    {
        return ApiCluster::canAccessApi();
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * @return array{
     *     api_enabled: bool,
     *     active_api_users: int,
     *     active_clients: int,
     *     expired_clients: int,
     *     revoked_clients: int,
     *     secrets_expiring_7d: int,
     *     secrets_expiring_30d: int,
     *     total_requests_24h: int,
     *     failed_requests_24h: int,
     *     success_rate_24h: float,
     *     avg_response_time_24h: float|null,
     *     rate_limit: int,
     *     logging_enabled: bool,
     *     slow_threshold_ms: int,
     *     retention_days: int|null,
     *     sampling_enabled: bool,
     *     sampling_rate: float,
     *     notifications_enabled: bool,
     *     notification_intervals: array<int>,
     * }
     */
    public function getStats(): array
    {
        $now = Carbon::now();

        $serviceClients = fn () => OAuthClient::query()->serviceClients();

        $activeApiUsers = $serviceClients()->active($now)->distinct('owner_id')->count('owner_id');

        $activeClients = $serviceClients()->active($now)->count();

        $expiredClients = $serviceClients()
            ->where('revoked', false)
            ->where('secret_expires_at', '<=', $now)
            ->count();

        $revokedClients = $serviceClients()->where('revoked', true)->count();

        $secretsExpiring7d = $serviceClients()
            ->active($now)
            ->whereBetween('secret_expires_at', [$now, $now->copy()->addDays(7)])
            ->count();

        $secretsExpiring30d = $serviceClients()
            ->active($now)
            ->whereBetween('secret_expires_at', [$now, $now->copy()->addDays(30)])
            ->count();

        /**
         * One pass over the last day of request logs instead of a query per stat.
         *
         * @var object{total: int, failed: int, avg_duration: float|string|null} $requestStats24h
         */
        $requestStats24h = ApiRequestLog::query()
            ->where('created_at', '>=', $now->copy()->subDay())
            ->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(failure_reason) as failed')
            ->selectRaw('avg(duration_ms) as avg_duration')
            ->first();

        $totalRequests24h = (int) $requestStats24h->total;
        $failedRequests24h = (int) $requestStats24h->failed;

        $successRate24h = $totalRequests24h > 0
            ? round((($totalRequests24h - $failedRequests24h) / $totalRequests24h) * 100, 1)
            : 100.0;

        $avgResponseTime24h = $requestStats24h->avg_duration;

        return [
            'api_enabled' => (bool) config('api.enabled', true),
            'active_api_users' => $activeApiUsers,
            'active_clients' => $activeClients,
            'expired_clients' => $expiredClients,
            'revoked_clients' => $revokedClients,
            'secrets_expiring_7d' => $secretsExpiring7d,
            'secrets_expiring_30d' => $secretsExpiring30d,
            'total_requests_24h' => $totalRequests24h,
            'failed_requests_24h' => $failedRequests24h,
            'success_rate_24h' => $successRate24h,
            'avg_response_time_24h' => $avgResponseTime24h !== null ? round((float) $avgResponseTime24h, 1) : null,
            'rate_limit' => (int) config('rate-limiting.api.per_minute', 1800),
            'logging_enabled' => (bool) config('api.request_logging.enabled', true),
            'slow_threshold_ms' => (int) config('api.request_logging.slow_request_threshold_ms', 500),
            'retention_days' => is_numeric($retention = config('platform.retention.api_request_logs')) ? (int) $retention : null,
            'sampling_enabled' => (bool) config('api.request_logging.sampling.enabled', false),
            'sampling_rate' => (float) config('api.request_logging.sampling.rate', 1.0),
            'notifications_enabled' => (bool) config('api.client_secret_expiration_notifications.enabled', true),
            'notification_intervals' => config('api.client_secret_expiration_notifications.intervals', []),
        ];
    }

    public function formatNumber(int $value): string
    {
        return (string) Number::abbreviate($value);
    }
}
