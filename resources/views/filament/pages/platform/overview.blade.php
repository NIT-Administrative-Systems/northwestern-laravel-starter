<x-filament-panels::page>
    @php
        $healthResults = $this->getHealthResults();
        $healthSummary = $this->getHealthSummary();
        $lastChecked = $this->getHealthLastChecked();
        $totalChecks = array_sum($healthSummary);
        $allPassing = $healthSummary['failed'] === 0 && $healthSummary['warning'] === 0;
    @endphp

    {{-- Health Status Banner --}}
    @if ($healthResults)
        <div
             class="{{ $allPassing ? 'border-success-300 bg-success-50 dark:border-success-500/40 dark:bg-success-500/10' : 'border-warning-300 bg-warning-50 dark:border-warning-500/40 dark:bg-warning-500/10' }} rounded-xl border p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <x-filament::icon class="{{ $allPassing ? 'text-success-600 dark:text-success-400' : 'text-warning-600 dark:text-warning-400' }} h-6 w-6"
                                      icon="{{ $allPassing ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle' }}" />
                    <div>
                        <p
                           class="{{ $allPassing ? 'text-success-800 dark:text-success-200' : 'text-warning-800 dark:text-warning-200' }} font-semibold">
                            {{ $allPassing ? 'All Systems Operational' : 'Attention Required' }}
                        </p>
                        <p
                           class="{{ $allPassing ? 'text-success-600 dark:text-success-300' : 'text-warning-600 dark:text-warning-300' }} text-sm">
                            {{ $healthSummary['ok'] }} of {{ $totalChecks }} checks passing
                            @if ($lastChecked)
                                &middot; Last checked {{ $lastChecked->diffForHumans() }}
                            @endif
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if ($healthSummary['ok'] > 0)
                        <span
                              class="bg-success-100 text-success-800 dark:bg-success-500/20 dark:text-success-300 inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium">
                            <span class="bg-success-500 h-1.5 w-1.5 rounded-full"></span>
                            {{ $healthSummary['ok'] }} OK
                        </span>
                    @endif
                    @if ($healthSummary['warning'] > 0)
                        <span
                              class="bg-warning-100 text-warning-800 dark:bg-warning-500/20 dark:text-warning-300 inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium">
                            <span class="bg-warning-500 h-1.5 w-1.5 rounded-full"></span>
                            {{ $healthSummary['warning'] }} Warning
                        </span>
                    @endif
                    @if ($healthSummary['failed'] > 0)
                        <span
                              class="bg-danger-100 text-danger-800 dark:bg-danger-500/20 dark:text-danger-300 inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium">
                            <span class="bg-danger-500 h-1.5 w-1.5 rounded-full"></span>
                            {{ $healthSummary['failed'] }} Failed
                        </span>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-3">
                <x-filament::icon class="h-6 w-6 text-gray-400" icon="heroicon-o-clock" />
                <div>
                    <p class="font-semibold text-gray-700 dark:text-gray-300">Health Checks Pending</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Health checks run every minute. Results will appear after the first run.
                    </p>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Left Column --}}
        <div class="space-y-6">
            {{-- Health Checks --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-filament::icon class="text-primary-500 h-5 w-5" icon="heroicon-o-heart" />
                        <span>Health Checks</span>
                    </div>
                </x-slot>

                @if ($healthResults && $healthResults->storedCheckResults->isNotEmpty())
                    <div class="space-y-3">
                        @foreach ($healthResults->storedCheckResults as $result)
                            @php
                                $formatted = $this->formatHealthResult($result);
                                $colorClasses = match ($formatted['color']) {
                                    'success' => [
                                        'iconBg' => 'bg-success-100 dark:bg-success-500/20',
                                        'iconText' => 'text-success-600 dark:text-success-400',
                                        'badge' =>
                                            'bg-success-100 text-success-800 dark:bg-success-500/20 dark:text-success-300',
                                    ],
                                    'warning' => [
                                        'iconBg' => 'bg-warning-100 dark:bg-warning-500/20',
                                        'iconText' => 'text-warning-600 dark:text-warning-400',
                                        'badge' =>
                                            'bg-warning-100 text-warning-800 dark:bg-warning-500/20 dark:text-warning-300',
                                    ],
                                    'danger' => [
                                        'iconBg' => 'bg-danger-100 dark:bg-danger-500/20',
                                        'iconText' => 'text-danger-600 dark:text-danger-400',
                                        'badge' =>
                                            'bg-danger-100 text-danger-800 dark:bg-danger-500/20 dark:text-danger-300',
                                    ],
                                    default => [
                                        'iconBg' => 'bg-gray-100 dark:bg-gray-700',
                                        'iconText' => 'text-gray-500 dark:text-gray-400',
                                        'badge' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                    ],
                                };
                            @endphp
                            <div
                                 class="flex items-start gap-3 rounded-lg border border-gray-100 bg-gray-50/50 p-3 dark:border-gray-800 dark:bg-gray-800/50">
                                <div
                                     class="{{ $colorClasses['iconBg'] }} flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full">
                                    <x-filament::icon :icon="$formatted['icon']" @class(['h-4 w-4', $colorClasses['iconText']]) />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="font-medium text-gray-900 dark:text-white">
                                            {{ $formatted['label'] }}
                                        </p>
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                            $colorClasses['badge'],
                                        ])>
                                            {{ $formatted['summary'] ?: ucfirst($formatted['status']) }}
                                        </span>
                                    </div>
                                    @if ($formatted['message'])
                                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                            {{ $formatted['message'] }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-6 text-center">
                        <x-filament::icon class="mx-auto h-8 w-8 text-gray-400" icon="heroicon-o-clock" />
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            No health check results available yet.
                        </p>
                    </div>
                @endif
            </x-filament::section>

            {{-- Integrations --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-filament::icon class="text-primary-500 h-5 w-5" icon="heroicon-o-puzzle-piece" />
                        <span>Integrations</span>
                    </div>
                </x-slot>

                @php
                    $integrations = $this->getIntegrations();
                @endphp

                @if (count($integrations) > 0)
                    <div class="space-y-3">
                        @foreach ($integrations as $integration)
                            <div
                                 class="flex items-center gap-3 rounded-lg border border-gray-100 bg-gray-50/50 p-3 dark:border-gray-800 dark:bg-gray-800/50">
                                <div
                                     class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700">
                                    <x-filament::icon class="h-5 w-5 text-gray-600 dark:text-gray-300"
                                                      :icon="$integration['icon']" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <p class="font-medium text-gray-900 dark:text-white">
                                            {{ $integration['name'] }}
                                        </p>
                                        @if ($integration['status'] === 'live')
                                            <span
                                                  class="bg-success-100 text-success-800 dark:bg-success-500/20 dark:text-success-300 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium">
                                                <span
                                                      class="bg-success-500 h-1.5 w-1.5 animate-pulse rounded-full"></span>
                                                Live
                                            </span>
                                        @elseif($integration['status'] === 'mock')
                                            <span
                                                  class="bg-warning-100 text-warning-800 dark:bg-warning-500/20 dark:text-warning-300 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium">
                                                <span class="bg-warning-500 h-1.5 w-1.5 rounded-full"></span>
                                                Mock
                                            </span>
                                        @else
                                            <span
                                                  class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                                Disabled
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mt-1 flex items-center gap-1.5">
                                        <x-filament::icon class="h-3 w-3 text-gray-400" icon="heroicon-o-link" />
                                        <code
                                              class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $integration['url'] }}</code>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-4 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">No integrations configured.</p>
                    </div>
                @endif
            </x-filament::section>
        </div>

        {{-- Right Column --}}
        <div class="space-y-6">
            {{-- Environment --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-filament::icon class="text-primary-500 h-5 w-5" icon="heroicon-o-server" />
                        <span>Environment</span>
                    </div>
                </x-slot>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($this->getEnvironmentInfo() as $label => $info)
                        <div class="flex items-center justify-between py-2 first:pt-0 last:pb-0">
                            <span
                                  class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</span>
                            <span @class([
                                'text-sm font-medium text-gray-900 dark:text-white',
                                'font-mono' => $info['mono'] ?? false,
                            ])>{{ $info['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            {{-- Services --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-filament::icon class="text-primary-500 h-5 w-5" icon="heroicon-o-cog-6-tooth" />
                        <span>Services</span>
                    </div>
                </x-slot>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($this->getServicesInfo() as $label => $info)
                        <div class="flex items-center justify-between py-2 first:pt-0 last:pb-0">
                            <span
                                  class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</span>
                            <span @class([
                                'text-sm font-medium text-gray-900 dark:text-white',
                                'font-mono' => $info['mono'] ?? false,
                            ])>{{ $info['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            {{-- Storage Info --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-filament::icon class="text-primary-500 h-5 w-5" icon="heroicon-o-circle-stack" />
                        <span>Storage</span>
                    </div>
                </x-slot>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($this->getStorageInfo() as $label => $info)
                        <div class="flex items-center justify-between py-2 first:pt-0 last:pb-0">
                            <span
                                  class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</span>
                            <span @class([
                                'text-sm font-medium text-gray-900 dark:text-white',
                                'font-mono' => $info['mono'] ?? false,
                            ])>{{ $info['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            {{-- Error Tracking / Observability --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-filament::icon class="text-primary-500 h-5 w-5" icon="heroicon-o-bug-ant" />
                        <span>Error Tracking</span>
                    </div>
                </x-slot>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($this->getObservabilityInfo() as $label => $info)
                        <div class="flex items-center justify-between py-2 first:pt-0 last:pb-0">
                            <span
                                  class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</span>
                            @if ($label === 'Sentry')
                                <span
                                      class="{{ $info['value'] === 'Enabled' ? 'bg-success-100 text-success-800 dark:bg-success-500/20 dark:text-success-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }} inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium">
                                    <span
                                          class="{{ $info['value'] === 'Enabled' ? 'bg-success-500' : 'bg-gray-400' }} h-1.5 w-1.5 rounded-full"></span>
                                    {{ $info['value'] }}
                                </span>
                            @else
                                <span @class([
                                    'text-sm font-medium text-gray-900 dark:text-white',
                                    'font-mono' => $info['mono'] ?? false,
                                ])>{{ $info['value'] }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

        </div>
    </div>
</x-filament-panels::page>
