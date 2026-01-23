<x-filament-panels::page>
    @php
        $stats = $this->getStats();
    @endphp

    {{-- API Status Banner --}}
    @if (!$stats['api_enabled'])
        <div
             class="border-danger-300 bg-danger-50 dark:border-danger-500/40 dark:bg-danger-500/10 rounded-xl border p-4">
            <div class="flex items-center gap-3">
                <x-filament::icon class="text-danger-500 h-6 w-6" icon="heroicon-o-x-circle" />
                <div>
                    <p class="text-danger-800 dark:text-danger-200 font-semibold">API Disabled</p>
                    <p class="text-danger-600 dark:text-danger-300 text-sm">
                        All API routes are currently responding with 503 Service Unavailable.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Main Stats Grid --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Active API Users --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-center gap-3">
                <div class="bg-primary-50 dark:bg-primary-500/10 flex h-10 w-10 items-center justify-center rounded-lg">
                    <x-filament::icon class="text-primary-600 dark:text-primary-400 h-5 w-5" icon="heroicon-o-users" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active API Users</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['active_api_users'] }}</p>
                </div>
            </div>
        </div>

        {{-- Total Requests (24h) --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-center gap-3">
                <div class="bg-success-50 dark:bg-success-500/10 flex h-10 w-10 items-center justify-center rounded-lg">
                    <x-filament::icon class="text-success-600 dark:text-success-400 h-5 w-5"
                                      icon="heroicon-o-arrow-trending-up" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Requests (24h)</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $this->formatNumber($stats['total_requests_24h']) }}</p>
                </div>
            </div>
        </div>

        {{-- Success Rate --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-center gap-3">
                <div
                     class="{{ $stats['success_rate_24h'] >= 99 ? 'bg-success-50 dark:bg-success-500/10' : ($stats['success_rate_24h'] >= 95 ? 'bg-warning-50 dark:bg-warning-500/10' : 'bg-danger-50 dark:bg-danger-500/10') }} flex h-10 w-10 items-center justify-center rounded-lg">
                    <x-filament::icon class="{{ $stats['success_rate_24h'] >= 99 ? 'text-success-600 dark:text-success-400' : ($stats['success_rate_24h'] >= 95 ? 'text-warning-600 dark:text-warning-400' : 'text-danger-600 dark:text-danger-400') }} h-5 w-5"
                                      icon="heroicon-o-check-badge" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Success Rate (24h)</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['success_rate_24h'] }}%</p>
                </div>
            </div>
        </div>

        {{-- Avg Response Time --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-center gap-3">
                <div class="bg-info-50 dark:bg-info-500/10 flex h-10 w-10 items-center justify-center rounded-lg">
                    <x-filament::icon class="text-info-600 dark:text-info-400 h-5 w-5" icon="heroicon-o-clock" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Response (24h)</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $stats['avg_response_time_24h'] !== null ? $stats['avg_response_time_24h'] . 'ms' : 'N/A' }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Left Column: Token Stats --}}
        <div class="space-y-6">
            {{-- Token Overview --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-filament::icon class="h-5 w-5 text-gray-400" icon="heroicon-o-key" />
                        <span>Access Tokens</span>
                    </div>
                </x-slot>

                <div class="space-y-4">
                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div class="bg-success-50 dark:bg-success-500/10 rounded-lg p-3">
                            <p class="text-success-600 dark:text-success-400 text-2xl font-bold">
                                {{ $stats['active_tokens'] }}</p>
                            <p class="text-success-700 dark:text-success-300 text-xs">Active</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                            <p class="text-2xl font-bold text-gray-600 dark:text-gray-400">
                                {{ $stats['expired_tokens'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Expired</p>
                        </div>
                        <div class="bg-danger-50 dark:bg-danger-500/10 rounded-lg p-3">
                            <p class="text-danger-600 dark:text-danger-400 text-2xl font-bold">
                                {{ $stats['revoked_tokens'] }}</p>
                            <p class="text-danger-700 dark:text-danger-300 text-xs">Revoked</p>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-4 dark:border-gray-800">
                        <p class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">Expiring Soon</p>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Within 7 days</span>
                                <span
                                      class="{{ $stats['tokens_expiring_7d'] > 0 ? 'bg-warning-100 text-warning-800 dark:bg-warning-500/20 dark:text-warning-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }} rounded-full px-2 py-0.5 text-xs font-medium">
                                    {{ $stats['tokens_expiring_7d'] }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Within 30 days</span>
                                <span
                                      class="{{ $stats['tokens_expiring_30d'] > 0 ? 'bg-info-100 text-info-800 dark:bg-info-500/20 dark:text-info-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }} rounded-full px-2 py-0.5 text-xs font-medium">
                                    {{ $stats['tokens_expiring_30d'] }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </x-filament::section>

        </div>

        {{-- Right Column: Configuration --}}
        <div class="space-y-6">
            {{-- Configuration Summary --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-filament::icon class="h-5 w-5 text-gray-400" icon="heroicon-o-cog-6-tooth" />
                        <span>Configuration</span>
                    </div>
                </x-slot>

                <div class="space-y-4">
                    {{-- Rate Limit --}}
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <x-filament::icon class="h-4 w-4 text-gray-400" icon="heroicon-o-shield-check" />
                            <span class="text-sm text-gray-600 dark:text-gray-400">Rate Limit</span>
                        </div>
                        <span
                              class="text-sm font-medium text-gray-900 dark:text-white">{{ number_format($stats['rate_limit']) }}/min</span>
                    </div>

                    {{-- Request Logging --}}
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <x-filament::icon class="h-4 w-4 text-gray-400" icon="heroicon-o-document-text" />
                            <span class="text-sm text-gray-600 dark:text-gray-400">Request Logging</span>
                        </div>
                        <span
                              class="{{ $stats['logging_enabled'] ? 'bg-success-100 text-success-800 dark:bg-success-500/20 dark:text-success-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }} inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium">
                            <span
                                  class="{{ $stats['logging_enabled'] ? 'bg-success-500' : 'bg-gray-400' }} h-1.5 w-1.5 rounded-full"></span>
                            {{ $stats['logging_enabled'] ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>

                    {{-- Slow Threshold --}}
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <x-filament::icon class="h-4 w-4 text-gray-400" icon="heroicon-o-clock" />
                            <span class="text-sm text-gray-600 dark:text-gray-400">Slow Threshold</span>
                        </div>
                        <span
                              class="text-sm font-medium text-gray-900 dark:text-white">{{ $stats['slow_threshold_ms'] }}ms</span>
                    </div>

                    {{-- Log Retention --}}
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <x-filament::icon class="h-4 w-4 text-gray-400" icon="heroicon-o-calendar-days" />
                            <span class="text-sm text-gray-600 dark:text-gray-400">Log Retention</span>
                        </div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $stats['retention_days'] }}
                            days</span>
                    </div>

                    {{-- Sampling --}}
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <x-filament::icon class="h-4 w-4 text-gray-400" icon="heroicon-o-adjustments-horizontal" />
                            <span class="text-sm text-gray-600 dark:text-gray-400">Sampling</span>
                        </div>
                        <span
                              class="{{ $stats['sampling_enabled'] ? 'bg-success-100 text-success-800 dark:bg-success-500/20 dark:text-success-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }} inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium">
                            @if ($stats['sampling_enabled'])
                                {{ $stats['sampling_rate'] * 100 }}%
                            @else
                                Disabled
                            @endif
                        </span>
                    </div>

                    {{-- Expiration Notifications --}}
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <x-filament::icon class="h-4 w-4 text-gray-400" icon="heroicon-o-bell-alert" />
                            <span class="text-sm text-gray-600 dark:text-gray-400">Expiry Notifications</span>
                        </div>
                        <span
                              class="{{ $stats['notifications_enabled'] ? 'bg-success-100 text-success-800 dark:bg-success-500/20 dark:text-success-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }} inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium">
                            <span
                                  class="{{ $stats['notifications_enabled'] ? 'bg-success-500' : 'bg-gray-400' }} h-1.5 w-1.5 rounded-full"></span>
                            {{ $stats['notifications_enabled'] ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>

                    @if ($stats['notifications_enabled'] && count($stats['notification_intervals']) > 0)
                        <div class="border-t border-gray-100 pt-3 dark:border-gray-800">
                            <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Notification Intervals</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($stats['notification_intervals'] as $interval)
                                    <span
                                          class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                        {{ $interval }}d
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
