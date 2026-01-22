<?php

declare(strict_types=1);

namespace App\Filament\Clusters\ApiCluster\Pages;

use App\Domains\Auth\Models\AccessToken;
use App\Domains\Auth\Models\ApiRequestLog;
use App\Filament\Clusters\ApiCluster;
use BackedEnum;
use Carbon\Carbon;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class Reference extends Page
{
    protected static ?string $cluster = ApiCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Reference';

    protected static ?string $title = 'Reference';

    protected static ?string $slug = 'reference';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.clusters.api-cluster.pages.reference';

    protected ?string $subheading = 'API configuration reference';

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function content(Schema $schema): Schema
    {
        $stats = $this->getStats();

        return $schema
            ->components([
                Section::make('General')
                    ->icon(Heroicon::OutlinedChartBar)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('active_api_users')
                            ->label('Active API Users')
                            ->state($stats['active_api_users'])
                            ->icon(Heroicon::OutlinedUsers)
                            ->weight(FontWeight::SemiBold)
                            ->color('primary'),

                        TextEntry::make('total_requests_30d')
                            ->label('Total Requests (30 days)')
                            ->state(Number::abbreviate($stats['total_requests_30d']))
                            ->icon(Heroicon::OutlinedArrowTrendingUp)
                            ->weight(FontWeight::SemiBold)
                            ->color('primary'),

                        TextEntry::make('rate_limit')
                            ->label('Rate Limit')
                            ->state(Number::format((int) config('auth.api.rate_limit.max_attempts', 1800)))
                            ->icon(Heroicon::OutlinedShieldCheck)
                            ->suffix(' / min')
                            ->color('gray'),
                    ]),

                Section::make('Expiration Notifications')
                    ->icon(Heroicon::OutlinedBellAlert)
                    ->columns(4)
                    ->schema([
                        TextEntry::make('notifications_enabled')
                            ->label('Status')
                            ->badge()
                            ->state(config('auth.api.expiration_notifications.enabled', true) ? 'Enabled' : 'Disabled')
                            ->color(fn (string $state): string => $state === 'Enabled' ? 'success' : 'gray')
                            ->icon(fn (string $state) => $state === 'Enabled'
                                ? Heroicon::OutlinedCheckCircle
                                : Heroicon::OutlinedXCircle),

                        TextEntry::make('notification_intervals')
                            ->label('Intervals')
                            ->state($this->getNotificationIntervalsText())
                            ->icon(Heroicon::OutlinedBellAlert)
                            ->color('gray'),

                        TextEntry::make('tokens_expiring_7d')
                            ->label('Expiring in 7 Days')
                            ->state($stats['tokens_expiring_7d'])
                            ->icon(Heroicon::OutlinedExclamationCircle)
                            ->color($stats['tokens_expiring_7d'] > 0 ? 'warning' : 'gray'),

                        TextEntry::make('tokens_expiring_30d')
                            ->label('Expiring in 30 Days')
                            ->state($stats['tokens_expiring_30d'])
                            ->icon(Heroicon::OutlinedCalendar)
                            ->color($stats['tokens_expiring_30d'] > 0 ? 'info' : 'gray'),
                    ]),

                Section::make('Request Logging')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->columns(4)
                    ->schema([
                        TextEntry::make('logging_enabled')
                            ->label('Status')
                            ->badge()
                            ->state(config('auth.api.request_logging.enabled', true) ? 'Enabled' : 'Disabled')
                            ->color(fn (string $state): string => $state === 'Enabled' ? 'success' : 'gray')
                            ->icon(fn (string $state) => $state === 'Enabled'
                                ? Heroicon::OutlinedCheckCircle
                                : Heroicon::OutlinedXCircle),

                        TextEntry::make('slow_threshold')
                            ->label('Slow Request Threshold')
                            ->state((int) config('auth.api.request_logging.slow_request_threshold_ms', 500))
                            ->icon(Heroicon::OutlinedClock)
                            ->suffix(' ms')
                            ->color('warning'),

                        TextEntry::make('retention_days')
                            ->label('Log Retention')
                            ->state((int) config('auth.api.request_logging.retention_days', 90))
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->suffix(' days')
                            ->color('gray'),

                        TextEntry::make('sampling')
                            ->label('Sampling')
                            ->badge()
                            ->state($this->getSamplingText())
                            ->color($this->isSamplingEnabled() ? 'success' : 'danger')
                            ->icon($this->isSamplingEnabled()
                                ? Heroicon::OutlinedCheckCircle
                                : Heroicon::OutlinedXCircle),
                    ]),
            ]);
    }

    /**
     * @return array{
     *     active_api_users: int,
     *     tokens_expiring_7d: int,
     *     tokens_expiring_30d: int,
     *     total_requests_30d: int
     * }
     */
    protected function getStats(): array
    {
        $now = Carbon::now();
        $thirtyDaysAgo = $now->copy()->subDays(30);

        $activeApiUsers = AccessToken::query()
            ->distinct('user_id')
            ->active()
            ->count('user_id');

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

        $totalRequests30d = ApiRequestLog::query()
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        return [
            'active_api_users' => $activeApiUsers,
            'tokens_expiring_7d' => $tokensExpiring7d,
            'tokens_expiring_30d' => $tokensExpiring30d,
            'total_requests_30d' => $totalRequests30d,
        ];
    }

    protected function isSamplingEnabled(): bool
    {
        return (bool) (config('auth.api.request_logging.sampling.enabled', false));
    }

    protected function getSamplingText(): string
    {
        if (! $this->isSamplingEnabled()) {
            return 'Disabled';
        }

        $samplingRate = (float) (config('auth.api.request_logging.sampling.rate', 1.0));

        return 'Enabled (' . ($samplingRate * 100) . '%)';
    }

    protected function getNotificationIntervalsText(): string
    {
        /** @var array<int> $intervals */
        $intervals = config('auth.api.expiration_notifications.intervals', []);

        if (blank($intervals)) {
            return 'None configured';
        }

        return implode(', ', array_map(
            fn (int $d): string => $d . ' ' . Str::plural('day', $d),
            $intervals
        ));
    }
}
