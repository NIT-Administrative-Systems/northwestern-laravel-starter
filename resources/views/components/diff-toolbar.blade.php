@props([
    'auditUrl' => null,
])

<div class="flex flex-wrap items-center justify-between gap-2">
    <div class="flex flex-wrap items-center gap-2">
        <div class="inline-flex rounded-lg bg-gray-100 p-0.5 dark:bg-white/5"
             role="group"
             aria-label="Diff layout">
            <button class="rounded-md px-2.5 py-1 text-xs font-medium transition-all"
                    type="button"
                    x-on:click.stop="updateOption('diffStyle', 'split')"
                    :aria-pressed="diffStyle === 'split'"
                    :class="diffStyle === 'split'
                        ?
                        'bg-white text-gray-900 ring-1 ring-gray-200 dark:bg-white/10 dark:text-white dark:ring-white/10' :
                        'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                Split
            </button>
            <button class="rounded-md px-2.5 py-1 text-xs font-medium transition-all"
                    type="button"
                    x-on:click.stop="updateOption('diffStyle', 'unified')"
                    :aria-pressed="diffStyle === 'unified'"
                    :class="diffStyle === 'unified'
                        ?
                        'bg-white text-gray-900 ring-1 ring-gray-200 dark:bg-white/10 dark:text-white dark:ring-white/10' :
                        'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                Unified
            </button>
        </div>

        <div class="inline-flex rounded-lg bg-gray-100 p-0.5 dark:bg-white/5"
             role="group"
             aria-label="Text overflow">
            <button class="rounded-md px-2.5 py-1 text-xs font-medium transition-all"
                    type="button"
                    x-on:click.stop="updateOption('overflow', 'wrap')"
                    :aria-pressed="overflow === 'wrap'"
                    :class="overflow === 'wrap'
                        ?
                        'bg-white text-gray-900 ring-1 ring-gray-200 dark:bg-white/10 dark:text-white dark:ring-white/10' :
                        'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                Wrap
            </button>
            <button class="rounded-md px-2.5 py-1 text-xs font-medium transition-all"
                    type="button"
                    x-on:click.stop="updateOption('overflow', 'scroll')"
                    :aria-pressed="overflow === 'scroll'"
                    :class="overflow === 'scroll'
                        ?
                        'bg-white text-gray-900 ring-1 ring-gray-200 dark:bg-white/10 dark:text-white dark:ring-white/10' :
                        'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                Scroll
            </button>
        </div>
    </div>

    @if ($auditUrl)
        <a class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200"
           href="{{ $auditUrl }}"
           x-on:click.stop
           target="_blank">
            <x-heroicon-m-arrow-top-right-on-square class="h-3.5 w-3.5" />
            View full audit entry
        </a>
    @endif
</div>
