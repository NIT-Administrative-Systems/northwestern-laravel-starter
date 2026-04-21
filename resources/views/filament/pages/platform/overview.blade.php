<x-filament-panels::page>
    @php
        $healthResults = $this->getHealthResults();
        $healthSummary = $this->getHealthSummary();
        $lastChecked = $this->getHealthLastChecked();
        $totalChecks = array_sum($healthSummary);
        $allPassing = $healthResults && $healthSummary['failed'] === 0 && $healthSummary['warning'] === 0;

        $bannerTone = match (true) {
            !$healthResults => 'pending',
            $healthSummary['failed'] > 0 => 'danger',
            $healthSummary['warning'] > 0 => 'warning',
            default => 'success',
        };
        $bannerLabel = match ($bannerTone) {
            'success' => 'All systems operational',
            'warning' => 'Attention required',
            'danger' => 'Action required',
            default => 'Health checks pending',
        };

        $queueStatus = $this->getQueueStatus();
        $apiTraffic = $this->getApiTrafficSeries();
        $loginHeatmap = $this->getLoginHeatmap();
        $scheduledTasks = $this->getScheduledTasks();
    @endphp

    {{-- Status ribbon: single compact line, not a full-height banner --}}
    <div
         class="@container flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-lg border border-gray-950/5 bg-white px-4 py-2.5 dark:border-white/10 dark:bg-gray-900">
        <div class="flex min-w-0 items-center gap-2.5">
            <span @class([
                'size-2 shrink-0 rounded-full',
                'bg-success-500 animate-pulse' => $bannerTone === 'success',
                'bg-warning-500' => $bannerTone === 'warning',
                'bg-danger-500' => $bannerTone === 'danger',
                'bg-gray-400' => $bannerTone === 'pending',
            ])></span>
            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">
                {{ $bannerLabel }}
            </p>
            @if ($healthResults)
                <p class="@sm:block hidden text-sm tabular-nums text-gray-500 dark:text-gray-400">
                    <span class="mx-1 text-gray-300 dark:text-gray-700">·</span>
                    <span>{{ $healthSummary['ok'] }}/{{ $totalChecks }} healthy</span>
                    @if ($lastChecked)
                        <span class="mx-1 text-gray-300 dark:text-gray-700">·</span>
                        <span>Checked {{ $lastChecked->diffForHumans() }}</span>
                    @endif
                </p>
            @endif
        </div>

        @if ($healthSummary['warning'] > 0 || $healthSummary['failed'] > 0)
            <div class="flex items-center gap-1.5 tabular-nums">
                @if ($healthSummary['failed'] > 0)
                    <x-filament::badge color="danger" size="xs">
                        {{ $healthSummary['failed'] }} failed
                    </x-filament::badge>
                @endif
                @if ($healthSummary['warning'] > 0)
                    <x-filament::badge color="warning" size="xs">
                        {{ $healthSummary['warning'] }} warning
                    </x-filament::badge>
                @endif
            </div>
        @endif
    </div>

    {{-- Queue alert: rendered only when something is stuck. --}}
    @if ($queueStatus !== null)
        <div
             class="border-danger-300 bg-danger-50 dark:border-danger-500/40 dark:bg-danger-500/10 flex items-start gap-3 rounded-lg border px-4 py-3">
            <x-filament::icon class="text-danger-600 dark:text-danger-400 mt-0.5 size-5 shrink-0"
                              icon="heroicon-o-exclamation-triangle" />
            <div class="min-w-0 flex-1">
                <p class="text-danger-900 dark:text-danger-100 text-sm font-semibold">
                    Queue attention needed
                </p>
                <p class="text-danger-800 dark:text-danger-200 mt-0.5 text-sm tabular-nums">
                    @if ($queueStatus['failed'] > 0)
                        <span>{{ $queueStatus['failed'] }} failed</span>
                    @endif
                    @if ($queueStatus['failed'] > 0 && $queueStatus['pending'] > 0)
                        <span class="text-danger-400 mx-1">·</span>
                    @endif
                    @if ($queueStatus['pending'] > 0)
                        <span>{{ $queueStatus['pending'] }} pending</span>
                    @endif
                    @if ($queueStatus['oldest_pending_at'])
                        <span class="text-danger-400 mx-1">·</span>
                        <span>oldest queued
                            {{ $queueStatus['oldest_pending_at']->diffForHumans() }}</span>
                    @endif
                </p>

                @if ($queueStatus['latest_failure'])
                    <figure class="border-danger-300 dark:border-danger-500/40 mt-3 border-l-2 pl-3 text-sm">
                        <figcaption
                                    class="text-danger-700 dark:text-danger-300 flex items-baseline justify-between gap-3 text-xs tabular-nums">
                            <span class="truncate font-mono">
                                {{ $queueStatus['latest_failure']['job'] }}
                            </span>
                            <time title="{{ $queueStatus['latest_failure']['failed_at']->format(config('platform.datetime_display_format')) }}"
                                  datetime="{{ $queueStatus['latest_failure']['failed_at']->toIso8601String() }}">
                                {{ $queueStatus['latest_failure']['failed_at']->diffForHumans() }}
                            </time>
                        </figcaption>
                        <blockquote
                                    class="text-danger-900 dark:text-danger-100 mt-1 line-clamp-2 text-pretty font-mono text-sm">
                            {{ $queueStatus['latest_failure']['message'] }}
                        </blockquote>
                    </figure>
                @endif

                @if (!empty($queueStatus['top_pending']))
                    <dl
                        class="border-danger-300 dark:border-danger-500/40 mt-3 flex flex-wrap gap-x-4 gap-y-1 border-l-2 pl-3 text-sm tabular-nums">
                        @foreach ($queueStatus['top_pending'] as $jobName => $count)
                            <div class="flex items-baseline gap-1.5">
                                <dd class="text-danger-900 dark:text-danger-100 font-semibold">{{ $count }}</dd>
                                <dt class="text-danger-700 dark:text-danger-300 truncate font-mono text-xs">
                                    {{ $jobName }}
                                </dt>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>
        </div>
    @endif

    {{-- Main 2-column layout: active status (3) / reference config (2) --}}
    <div class="@container">
        <div class="@4xl:grid-cols-5 grid grid-cols-1 gap-6">
            {{-- Active status --}}
            <div class="@4xl:col-span-3 space-y-6">
                <x-filament::section heading="Health Checks"
                                     icon="heroicon-o-heart"
                                     compact>
                    @if ($healthResults && $healthResults->storedCheckResults->isNotEmpty())
                        <ul class="-my-2 divide-y divide-gray-950/5 dark:divide-white/5" role="list">
                            @foreach ($healthResults->storedCheckResults as $result)
                                @php $formatted = $this->formatHealthResult($result); @endphp
                                <li class="flex items-start gap-3 py-3">
                                    <span @class([
                                        'mt-1.5 size-2 shrink-0 rounded-full',
                                        'bg-success-500' => $formatted['color'] === 'success',
                                        'bg-warning-500' => $formatted['color'] === 'warning',
                                        'bg-danger-500' => $formatted['color'] === 'danger',
                                        'bg-gray-300 dark:bg-gray-600' => $formatted['color'] === 'gray',
                                    ])></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                                {{ $formatted['label'] }}
                                            </p>
                                            <x-filament::badge :color="$formatted['color']" size="xs">
                                                {{ $formatted['summary'] ?: ucfirst($formatted['status']) }}
                                            </x-filament::badge>
                                        </div>
                                        @if ($formatted['message'])
                                            <p class="mt-0.5 text-pretty text-sm text-gray-500 dark:text-gray-400">
                                                {{ $formatted['message'] }}
                                            </p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="flex flex-col items-center gap-2 py-6 text-center">
                            <x-filament::icon class="size-6 text-gray-400" icon="heroicon-o-clock" />
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                No health check results available yet.
                            </p>
                        </div>
                    @endif
                </x-filament::section>

                <x-filament::section heading="Integrations"
                                     icon="heroicon-o-puzzle-piece"
                                     compact>
                    @php $integrations = $this->getIntegrations(); @endphp

                    @if (count($integrations) > 0)
                        <ul class="-my-2 divide-y divide-gray-950/5 dark:divide-white/5" role="list">
                            @foreach ($integrations as $integration)
                                <li class="flex items-start gap-3 py-3">
                                    <x-filament::icon class="mt-0.5 size-4 shrink-0 text-gray-400" :icon="$integration['icon']" />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                                {{ $integration['name'] }}
                                            </p>
                                            @switch($integration['status'])
                                                @case('live')
                                                    <x-filament::badge color="success" size="xs">Live</x-filament::badge>
                                                @break

                                                @case('mock')
                                                    <x-filament::badge color="warning" size="xs">Mock</x-filament::badge>
                                                @break

                                                @default
                                                    <x-filament::badge color="gray"
                                                                       size="xs">Disabled</x-filament::badge>
                                            @endswitch
                                        </div>
                                        <p class="mt-0.5 truncate font-mono text-xs text-gray-500 dark:text-gray-400">
                                            {{ $integration['url'] }}
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            No integrations configured.
                        </p>
                    @endif
                </x-filament::section>

                @if ($loginHeatmap !== null)
                    <x-filament::section heading="Login Activity"
                                         icon="heroicon-o-calendar"
                                         compact>
                        <x-slot name="afterHeader">
                            <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400">
                                Last {{ $loginHeatmap['days'] }} days
                            </span>
                        </x-slot>

                        <div class="@container">
                            <div
                                 class="@md:grid @md:grid-cols-[minmax(0,1fr)_auto] @md:items-center flex flex-col gap-6">
                                {{-- Left: the heatmap, filling the remaining row width (its cells scale to match). --}}
                                <div class="flex min-w-0 flex-col gap-2">
                                    {{-- Hour axis. Four labels (0 / 6 / 12 / 18) aligned with their columns in the grid below. --}}
                                    <div
                                         class="grid grid-cols-[2rem_repeat(24,_minmax(0,1fr))] items-end gap-x-0.5 text-xs tabular-nums text-gray-400 dark:text-gray-500">
                                        <span></span>
                                        @for ($h = 0; $h < 24; $h++)
                                            <span class="text-center">
                                                {{ in_array($h, [0, 6, 12, 18], true) ? str_pad((string) $h, 2, '0', STR_PAD_LEFT) : '' }}
                                            </span>
                                        @endfor
                                    </div>

                                    {{-- Heatmap rows. Label column is 2rem so the 24 data columns get the rest of the width. --}}
                                    <div class="flex flex-col gap-0.5"
                                         role="img"
                                         aria-label="Login activity heatmap over the last {{ $loginHeatmap['days'] }} days">
                                        @foreach ($loginHeatmap['rows'] as $row)
                                            <div
                                                 class="grid grid-cols-[2rem_repeat(24,_minmax(0,1fr))] items-center gap-x-0.5">
                                                <span class="pr-2 text-right text-xs tabular-nums text-gray-400 dark:text-gray-500"
                                                      title="{{ $row['date']->format('l, F j, Y') }}">
                                                    {{ $row['label'] }}
                                                </span>
                                                @foreach ($row['cells'] as $cell)
                                                    <div title="{{ $cell['tooltip'] }}"
                                                         aria-label="{{ $cell['tooltip'] }}"
                                                         @class([
                                                             'aspect-square rounded-xs',
                                                             'bg-gray-950/5 dark:bg-white/5' => $cell['bucket'] === 0,
                                                             'bg-primary-200 dark:bg-primary-900' => $cell['bucket'] === 1,
                                                             'bg-primary-400 dark:bg-primary-700' => $cell['bucket'] === 2,
                                                             'bg-primary-600 dark:bg-primary-500' => $cell['bucket'] === 3,
                                                             'bg-primary-700 dark:bg-primary-400' => $cell['bucket'] === 4,
                                                         ])
                                                         x-tooltip.raw="{{ $cell['tooltip'] }}"></div>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Right: summary + legend sits at the end of the row at a natural (content-sized) width. --}}
                                <div class="@md:pl-6 flex w-48 shrink-0 flex-col gap-4">
                                    <div>
                                        <p class="text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">
                                            {{ number_format($loginHeatmap['total']) }}<span
                                                  class="ml-1 text-sm font-normal text-gray-500 dark:text-gray-400">{{ Str::plural('login', $loginHeatmap['total']) }}</span>
                                        </p>
                                        @if ($loginHeatmap['peak'])
                                            <p class="mt-1 text-sm tabular-nums text-gray-500 dark:text-gray-400">
                                                Peak
                                                <span
                                                      class="font-semibold text-gray-950 dark:text-white">{{ $loginHeatmap['peak']['count'] }}</span>
                                                at {{ $loginHeatmap['peak']['when']->format('D H:i') }}
                                            </p>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                        <span>Less</span>
                                        <span class="rounded-xs size-2.5 bg-gray-950/5 dark:bg-white/5"></span>
                                        <span class="rounded-xs bg-primary-200 dark:bg-primary-900 size-2.5"></span>
                                        <span class="rounded-xs bg-primary-400 dark:bg-primary-700 size-2.5"></span>
                                        <span class="rounded-xs bg-primary-600 dark:bg-primary-500 size-2.5"></span>
                                        <span class="rounded-xs bg-primary-700 dark:bg-primary-400 size-2.5"></span>
                                        <span>More</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-filament::section>
                @endif

                @if ($apiTraffic !== null)
                    @php
                        $counts = $apiTraffic['counts'];
                        $bucketCount = count($counts);
                        $peak = max($counts);
                        $scale = max($peak, 1);
                        $nowCount = $bucketCount > 0 ? $counts[$bucketCount - 1] : 0;

                        // Fixed-size viewBox keeps the chart maths integer-friendly; the wrapper
                        // element controls the on-screen size.
                        $viewW = 590;
                        $viewH = 64;
                        $padTop = 6;
                        $padBottom = 4;
                        $plotH = $viewH - $padTop - $padBottom;

                        $linePoints = [];
                        foreach ($counts as $i => $count) {
                            $x = $bucketCount > 1 ? $i * ($viewW / ($bucketCount - 1)) : 0;
                            $y = $padTop + (1 - $count / $scale) * $plotH;
                            $linePoints[] = number_format($x, 1, '.', '') . ',' . number_format($y, 1, '.', '');
                        }
                        $line = implode(' ', $linePoints);
                        $area = "0,{$viewH} " . $line . " {$viewW},{$viewH}";

                        // Percent-based y coordinate for the HTML "now" marker overlay,
                        // so the dot can be a real CSS circle instead of an ellipse
                        // stretched by preserveAspectRatio="none".
                        $dotTopPct = (($padTop + (1 - $nowCount / $scale) * $plotH) / $viewH) * 100;
                    @endphp
                    <x-filament::section heading="API Traffic"
                                         icon="heroicon-o-signal"
                                         compact>
                        <dl class="flex flex-wrap gap-x-8 gap-y-3">
                            <div>
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Requests</dt>
                                <dd class="text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">
                                    {{ number_format($apiTraffic['total']) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm text-gray-500 dark:text-gray-400">p95 latency</dt>
                                <dd class="text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">
                                    {{ number_format($apiTraffic['p95_ms']) }}<span
                                          class="ml-0.5 text-sm font-normal text-gray-500 dark:text-gray-400">ms</span>
                                </dd>
                            </div>
                        </dl>

                        <div class="text-primary-500 dark:text-primary-400 relative mt-5">
                            <svg class="h-16 w-full overflow-visible"
                                 role="img"
                                 aria-label="Requests per minute over the last 60 minutes"
                                 viewBox="0 0 {{ $viewW }} {{ $viewH }}"
                                 preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="api-traffic-fill"
                                                    x1="0"
                                                    x2="0"
                                                    y1="0"
                                                    y2="1">
                                        <stop offset="0%"
                                              stop-color="currentColor"
                                              stop-opacity="0.22" />
                                        <stop offset="100%"
                                              stop-color="currentColor"
                                              stop-opacity="0" />
                                    </linearGradient>
                                </defs>
                                <polygon fill="url(#api-traffic-fill)" points="{{ $area }}" />
                                <polyline fill="none"
                                          stroke="currentColor"
                                          stroke-width="1.5"
                                          stroke-linejoin="round"
                                          stroke-linecap="round"
                                          vector-effect="non-scaling-stroke"
                                          points="{{ $line }}" />
                            </svg>
                            <span class="bg-primary-500 dark:bg-primary-400 absolute right-0 size-2.5 -translate-y-1/2 translate-x-1/2 rounded-full ring-2 ring-white dark:ring-gray-900"
                                  aria-hidden="true"
                                  style="top: {{ number_format($dotTopPct, 2, '.', '') }}%;"></span>
                        </div>

                        <div
                             class="mt-2 flex items-baseline justify-between text-xs tabular-nums text-gray-500 dark:text-gray-400">
                            <span>60 min ago</span>
                            <span>peak {{ number_format($peak) }}/min</span>
                            <span>now</span>
                        </div>
                    </x-filament::section>
                @endif
            </div>

            {{-- Reference config --}}
            <div class="@4xl:col-span-2 space-y-6">
                <x-filament::section heading="Configuration"
                                     icon="heroicon-o-cog-6-tooth"
                                     compact>
                    <div class="space-y-6">
                        @php
                            $groups = [
                                ['label' => 'Environment', 'rows' => $this->getEnvironmentInfo()],
                                ['label' => 'Services', 'rows' => $this->getServicesInfo()],
                                ['label' => 'Storage', 'rows' => $this->getStorageInfo()],
                                ['label' => 'Error tracking', 'rows' => $this->getObservabilityInfo()],
                            ];
                        @endphp

                        @foreach ($groups as $group)
                            <section>
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $group['label'] }}
                                </h3>
                                <dl class="mt-2 divide-y divide-gray-950/5 dark:divide-white/5">
                                    @foreach ($group['rows'] as $label => $info)
                                        <div
                                             class="flex items-baseline justify-between gap-4 py-2 first:pt-0 last:pb-0">
                                            <dt class="shrink-0 text-sm font-medium text-gray-950 dark:text-white">
                                                {{ $label }}
                                            </dt>
                                            <dd title="{{ $info['value'] }}" @class([
                                                'min-w-0 truncate text-right text-sm text-gray-500 dark:text-gray-400',
                                                'font-mono' => $info['mono'] ?? false,
                                            ])>
                                                {{ $info['value'] }}
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </section>
                        @endforeach
                    </div>
                </x-filament::section>

                <x-filament::section heading="Feature Flags"
                                     icon="heroicon-o-flag"
                                     compact>
                    @php $flags = $this->getFeatureFlags(); @endphp
                    <ul class="-my-2 divide-y divide-gray-950/5 dark:divide-white/5" role="list">
                        @foreach ($flags as $flag)
                            <li class="flex items-center justify-between gap-4 py-2.5">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                        {{ $flag['label'] }}
                                    </p>
                                    <p class="truncate font-mono text-xs text-gray-500 dark:text-gray-400">
                                        {{ $flag['source'] }}
                                    </p>
                                </div>
                                <x-filament::badge :color="$flag['enabled'] ? 'success' : 'gray'" size="xs">
                                    {{ $flag['enabled'] ? 'Enabled' : 'Disabled' }}
                                </x-filament::badge>
                            </li>
                        @endforeach
                    </ul>
                </x-filament::section>
            </div>
        </div>
    </div>

    {{-- Scheduled tasks: full-width table, lives below the two-column grid --}}
    @if (count($scheduledTasks) > 0)
        <x-filament::section heading="Scheduled Tasks"
                             icon="heroicon-o-calendar-days"
                             compact>
            <x-slot name="afterHeader">
                <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400">
                    {{ count($scheduledTasks) }} {{ Str::plural('task', count($scheduledTasks)) }}
                </span>
            </x-slot>

            <div class="-mx-4 -my-2 overflow-x-auto whitespace-nowrap sm:-mx-6">
                <div class="inline-block min-w-full px-4 py-2 align-middle sm:px-6">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-950/5 dark:border-white/5">
                                <th class="py-2 pr-4 text-left font-medium text-gray-500 dark:text-gray-400">
                                    Command
                                </th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">
                                    Schedule
                                </th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">
                                    Next run
                                </th>
                                <th class="py-2 pl-4 text-left font-medium text-gray-500 dark:text-gray-400">
                                    Description
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-950/5 dark:divide-white/5">
                            @foreach ($scheduledTasks as $task)
                                <tr>
                                    <td class="py-2.5 pr-4 align-top">
                                        <span
                                              class="font-mono text-sm font-medium text-gray-950 dark:text-white">{{ $this->formatScheduledCommand($task['command']) }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 align-top">
                                        <code
                                              class="rounded bg-gray-950/5 px-1.5 py-0.5 font-mono text-xs text-gray-700 dark:bg-white/10 dark:text-gray-300">{{ $task['expression'] }}</code>
                                    </td>
                                    <td class="px-4 py-2.5 align-top">
                                        <span class="text-sm tabular-nums text-gray-950 dark:text-white">
                                            {{ $task['next_due_date_human'] ?? '—' }}
                                        </span>
                                        @if ($task['next_due_date'])
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $task['next_due_date'] }}
                                            </p>
                                        @endif
                                    </td>
                                    <td class="max-w-md whitespace-normal py-2.5 pl-4 align-top">
                                        <p class="text-pretty text-sm text-gray-500 dark:text-gray-400">
                                            {{ $task['description'] ?? '—' }}
                                        </p>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
