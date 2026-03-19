@php
    use App\Filament\Resources\Audits\AuditResource;
    use Illuminate\Support\Facades\Vite;

    $record = $getRecord();
    $oldValues = $record->old_values;
    $newValues = $record->new_values;
    $hasChanges = filled($oldValues) || filled($newValues);
    $auditUrl = AuditResource::getUrl('view', ['record' => $record]);
@endphp

@assets
    <script src="{{ Vite::asset('resources/js/audit-diff.ts') }}" type="module"></script>
@endassets

<div class="px-4 py-3">
    @if ($hasChanges)
        <div class="space-y-3"
             wire:ignore
             x-data="auditDiffViewer(
                 {{ Js::from($oldValues) }},
                 {{ Js::from($newValues) }}
             )"
             x-on:destroy="destroy">
            <x-diff-toolbar :audit-url="$auditUrl" />

            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-white/10" x-ref="diffContainer">
            </div>
        </div>
    @else
        <div class="flex items-center justify-between">
            <p class="text-sm italic text-gray-500 dark:text-gray-400">No changes recorded.</p>

            <a class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200"
               href="{{ $auditUrl }}"
               x-on:click.stop
               target="_blank">
                <x-heroicon-m-arrow-top-right-on-square class="h-3.5 w-3.5" />
                View full audit entry
            </a>
        </div>
    @endif
</div>
