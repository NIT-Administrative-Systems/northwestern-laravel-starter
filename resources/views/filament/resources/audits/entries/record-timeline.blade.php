@php
    use App\Domains\User\Models\Audit;
    use App\Filament\Resources\Audits\AuditResource;
    use Illuminate\Support\Str;

    $record = $getRecord();

    $audits = Audit::query()
        ->where('auditable_type', $record->auditable_type)
        ->where('auditable_id', $record->auditable_id)
        ->with('user:id,username')
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->limit(50)
        ->get();

    $totalCount = null;
    if ($audits->count() === 50) {
        $totalCount = Audit::query()
            ->where('auditable_type', $record->auditable_type)
            ->where('auditable_id', $record->auditable_id)
            ->count();
    }
@endphp

<div class="space-y-0">
    @forelse ($audits as $audit)
        @php
            $isCurrent = $audit->id === $record->id;
            $eventColor = match ($audit->event) {
                'created', 'restored', 'role_assigned' => 'success',
                'deleted', 'role_removed' => 'danger',
                'updated', 'permissions_modified' => 'warning',
                default => 'gray',
            };
        @endphp

        <div class="{{ !$loop->last ? 'pb-5' : '' }} relative flex gap-x-3">
            @unless ($loop->last)
                <div class="absolute -bottom-0 left-[0.4375rem] top-5 w-px bg-gray-200 dark:bg-white/10"></div>
            @endunless

            <div class="relative flex-none">
                <div @class([
                    'mt-0.5 h-3.5 w-3.5 rounded-full',
                    'ring-2 ring-primary-500 ring-offset-2 ring-offset-white dark:ring-offset-gray-900' => $isCurrent,
                    'bg-success-500' => $eventColor === 'success',
                    'bg-danger-500' => $eventColor === 'danger',
                    'bg-warning-500' => $eventColor === 'warning',
                    'bg-gray-300 dark:bg-gray-600' => $eventColor === 'gray',
                ])></div>
            </div>

            <div class="-mt-0.5 min-w-0 flex-1">
                @if ($isCurrent)
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ Str::of($audit->event)->replace('_', ' ')->title() }}
                        </span>
                        <span
                              class="bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-400 inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium">
                            Current
                        </span>
                    </div>
                @else
                    <a class="hover:text-primary-600 dark:hover:text-primary-400 text-sm font-medium text-gray-600 transition-colors dark:text-gray-400"
                       href="{{ AuditResource::getUrl('view', ['record' => $audit]) }}">
                        {{ Str::of($audit->event)->replace('_', ' ')->title() }}
                    </a>
                @endif

                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    @datetime($audit->created_at)
                    @if ($audit->user)
                        <span class="text-gray-400 dark:text-gray-500">&middot;</span>
                        {{ $audit->user->username }}
                    @endif
                </p>
            </div>
        </div>
    @empty
        <p class="text-sm italic text-gray-500 dark:text-gray-400">No audit history found for this record.</p>
    @endforelse

    @if ($totalCount && $totalCount > 50)
        <p class="pt-3 text-xs text-gray-400 dark:text-gray-500">
            Showing 50 of {{ number_format($totalCount) }} entries.
        </p>
    @endif
</div>
