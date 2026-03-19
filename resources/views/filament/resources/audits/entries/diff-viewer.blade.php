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
        <div class="mb-3">
            <x-diff-toolbar />
        </div>

        {{-- Diff container --}}
        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-white/10" x-ref="diffContainer"></div>
    </div>
@else
    <p class="text-sm italic text-gray-500 dark:text-gray-400">No changes recorded.</p>
@endif
