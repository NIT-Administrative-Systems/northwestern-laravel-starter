@php
    if (!function_exists('getColorClass')) {
        function getColorClass(string $color): string
        {
            return match ($color) {
                'success' => 'text-nu-dark-green dark:text-nu-green',
                'warning' => 'text-amber-700 dark:text-amber-400',
                'danger' => 'text-red-700 dark:text-red-400',
                default => 'text-gray-500 dark:text-gray-400',
            };
        }
    }

    $singleColumn = empty($rightColumns);
    $leftMeta = $leftMeta ?? null;
@endphp

{{-- The columns follow the widget's width, not the screen's, so a path or a range never breaks mid-word. --}}
<div class="@container">
    <div class="{{ $singleColumn ? 'grid-cols-1' : 'grid-cols-1 @md:grid-cols-2' }} @md:gap-6 grid gap-4 text-sm">
        <div class="flex min-w-0 flex-col">
            <div class="mb-1 text-[0.7rem] font-medium uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                {{ $leftLabel }}
            </div>

            <div class="truncate font-mono text-lg font-semibold leading-tight text-gray-900 dark:text-gray-100"
                 title="{{ $leftValue }}">
                {{ $leftValue }}
            </div>

            @if ($leftMeta)
                <div class="mt-0.5 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                    {{ $leftMeta }}
                </div>
            @endif
        </div>

        @unless ($singleColumn)
            <div class="{{ $rightGridClass }} grid gap-3">
                @foreach ($rightColumns as $column)
                    <div class="flex flex-col items-center justify-center">
                        <div
                             class="{{ getColorClass($column['color']) }} mb-1 whitespace-nowrap text-[0.7rem] font-semibold tracking-[0.14em]">
                            {{ $column['label'] }}
                        </div>
                        <div
                             class="whitespace-nowrap font-mono text-lg font-semibold leading-tight text-gray-900 dark:text-gray-100">
                            {{ $column['value'] }}
                        </div>

                        @if (!empty($column['meta']))
                            <div class="mt-0.5 whitespace-nowrap text-[0.65rem] text-gray-500 dark:text-gray-400">
                                {{ $column['meta'] }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endunless
    </div>
</div>
