@php
    if (!function_exists('getColorClass')) {
        function getColorClass(string $color): string
        {
            return match ($color) {
                'success' => 'text-green-600 dark:text-green-400',
                'warning' => 'text-yellow-600 dark:text-yellow-400',
                'danger' => 'text-red-600 dark:text-red-400',
                default => 'text-gray-500 dark:text-gray-400',
            };
        }
    }

    $singleColumn = empty($rightColumns);
    $leftMeta = $leftMeta ?? null;
@endphp

<div class="{{ $singleColumn ? 'grid-cols-1' : 'grid-cols-2' }} grid gap-6 text-sm">
    <div class="flex flex-col">
        <div class="mb-1 text-[0.7rem] font-medium uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
            {{ $leftLabel }}
        </div>

        <div
             class="whitespace-normal break-words font-mono text-lg font-semibold leading-tight text-gray-900 dark:text-gray-100">
            {{ $leftValue }}

            @if ($leftMeta)
                <span class="ml-1 align-baseline text-xs font-normal text-gray-500 dark:text-gray-400">
                    ({{ $leftMeta }})
                </span>
            @endif
        </div>
    </div>

    @unless ($singleColumn)
        <div class="{{ $rightGridClass }} grid gap-3">
            @foreach ($rightColumns as $column)
                <div class="flex flex-col items-center justify-center">
                    <div class="{{ getColorClass($column['color']) }} mb-1 text-[0.7rem] font-semibold tracking-[0.14em]">
                        {{ $column['label'] }}
                    </div>
                    <div class="font-mono text-lg font-semibold leading-tight text-gray-900 dark:text-gray-100">
                        {{ $column['value'] }}
                    </div>
                </div>
            @endforeach
        </div>
    @endunless
</div>
