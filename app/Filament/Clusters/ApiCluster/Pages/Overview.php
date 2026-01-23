<?php

declare(strict_types=1);

namespace App\Filament\Clusters\ApiCluster\Pages;

use App\Domains\Auth\Models\AccessToken;
use App\Domains\Auth\Models\ApiRequestLog;
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

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * @return array{
     *     api_enabled: bool,
     *     active_api_users: int,
     *     active_tokens: int,
     *     expired_tokens: int,
     *     revoked_tokens: int,
     *     tokens_expiring_7d: int,
     *     tokens_expiring_30d: int,
     *     total_requests_24h: int,
     *     failed_requests_24h: int,
     *     success_rate_24h: float,
     *     avg_response_time_24h: float|null,
     *     rate_limit: int,
     *     logging_enabled: bool,
     *     slow_threshold_ms: int,
     *     retention_days: int,
     *     sampling_enabled: bool,
     *     sampling_rate: float,
     *     notifications_enabled: bool,
     *     notification_intervals: array<int>,
     * }
     */
    public function getStats(): array
    {
        $now = Carbon::now();

        $activeApiUsers = AccessToken::query()
            ->distinct('user_id')
            ->active()
            ->count('user_id');

        $activeTokens = AccessToken::query()->active()->count();

        $expiredTokens = AccessToken::query()
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->count();

        $revokedTokens = AccessToken::query()
            ->whereNotNull('revoked_at')
            ->count();

        $tokensExpiring7d = AccessToken::query()
            ->active()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$now, $now->copy()->addDays(7)])
            ->count();

        $tokensExpiring30d = AccessToken::query()
            ->active()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$now, $now->copy()->addDays(30)])
            ->count();

        $totalRequests24h = ApiRequestLog::query()
            ->where('created_at', '>=', $now->copy()->subDay())
            ->count();

        $failedRequests24h = ApiRequestLog::query()
            ->where('created_at', '>=', $now->copy()->subDay())
            ->whereNotNull('failure_reason')
            ->count();

        $successRate24h = $totalRequests24h > 0
            ? round((($totalRequests24h - $failedRequests24h) / $totalRequests24h) * 100, 1)
            : 100.0;

        $avgResponseTime24h = ApiRequestLog::query()
            ->where('created_at', '>=', $now->copy()->subDay())
            ->whereNotNull('duration_ms')
            ->avg('duration_ms');

        return [
            'api_enabled' => (bool) config('auth.api.enabled', true),
            'active_api_users' => $activeApiUsers,
            'active_tokens' => $activeTokens,
            'expired_tokens' => $expiredTokens,
            'revoked_tokens' => $revokedTokens,
            'tokens_expiring_7d' => $tokensExpiring7d,
            'tokens_expiring_30d' => $tokensExpiring30d,
            'total_requests_24h' => $totalRequests24h,
            'failed_requests_24h' => $failedRequests24h,
            'success_rate_24h' => $successRate24h,
            'avg_response_time_24h' => $avgResponseTime24h !== null ? round((float) $avgResponseTime24h, 1) : null,
            'rate_limit' => (int) config('auth.api.rate_limit.max_attempts', 1800),
            'logging_enabled' => (bool) config('auth.api.request_logging.enabled', true),
            'slow_threshold_ms' => (int) config('auth.api.request_logging.slow_request_threshold_ms', 500),
            'retention_days' => (int) config('auth.api.request_logging.retention_days', 90),
            'sampling_enabled' => (bool) config('auth.api.request_logging.sampling.enabled', false),
            'sampling_rate' => (float) config('auth.api.request_logging.sampling.rate', 1.0),
            'notifications_enabled' => (bool) config('auth.api.expiration_notifications.enabled', true),
            'notification_intervals' => config('auth.api.expiration_notifications.intervals', []),
        ];
    }

    public function formatNumber(int $value): string
    {
        return Number::abbreviate($value);
    }
}
