@php
    use Illuminate\Support\Facades\Vite;

    $record = $getRecord();
    $oldValues = $record->old_values;
    $newValues = $record->new_values;
    $hasChanges = filled($oldValues) || filled($newValues);
@endphp

@assets
    <script src="{{ Vite::asset('resources/js/audit-diff.ts') }}" type="module"></script>
@endassets

@if ($hasChanges)
    <div x-data="auditDiffViewer(
        {{ Js::from($oldValues) }},
        {{ Js::from($newValues) }}
    )" x-on:destroy="destroy">
        {{-- Toolbar --}}
        <div class="mb-3 flex flex-wrap items-center gap-2">
            {{-- Layout toggle --}}
            <div class="inline-flex rounded-lg bg-gray-100 p-0.5 dark:bg-white/5"
                 role="group"
                 aria-label="Diff layout">
                <button class="rounded-md px-2.5 py-1 text-xs font-medium transition-all"
                        type="button"
                        x-on:click="updateOption('diffStyle', 'split')"
                        :aria-pressed="diffStyle === 'split'"
                        :class="diffStyle === 'split'
                            ?
                            'bg-white text-gray-900 shadow-sm dark:bg-white/10 dark:text-white' :
                            'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                    Split
                </button>
                <button class="rounded-md px-2.5 py-1 text-xs font-medium transition-all"
                        type="button"
                        x-on:click="updateOption('diffStyle', 'unified')"
                        :aria-pressed="diffStyle === 'unified'"
                        :class="diffStyle === 'unified'
                            ?
                            'bg-white text-gray-900 shadow-sm dark:bg-white/10 dark:text-white' :
                            'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                    Unified
                </button>
            </div>

            {{-- Overflow toggle --}}
            <div class="inline-flex rounded-lg bg-gray-100 p-0.5 dark:bg-white/5"
                 role="group"
                 aria-label="Text overflow">
                <button class="rounded-md px-2.5 py-1 text-xs font-medium transition-all"
                        type="button"
                        x-on:click="updateOption('overflow', 'wrap')"
                        :aria-pressed="overflow === 'wrap'"
                        :class="overflow === 'wrap'
                            ?
                            'bg-white text-gray-900 shadow-sm dark:bg-white/10 dark:text-white' :
                            'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                    Wrap
                </button>
                <button class="rounded-md px-2.5 py-1 text-xs font-medium transition-all"
                        type="button"
                        x-on:click="updateOption('overflow', 'scroll')"
                        :aria-pressed="overflow === 'scroll'"
                        :class="overflow === 'scroll'
                            ?
                            'bg-white text-gray-900 shadow-sm dark:bg-white/10 dark:text-white' :
                            'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                    Scroll
                </button>
            </div>
        </div>

        {{-- Diff container --}}
        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-white/10" x-ref="diffContainer"></div>
    </div>
@else
    <p class="text-sm italic text-gray-500 dark:text-gray-400">No changes recorded.</p>
@endif
