<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-start gap-6">
            <div class="flex-1">
                <h2 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                    {{ config('app.name') }}
                </h2>

                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    This dashboard is yours to customize. Add widgets, stats, and charts to create the perfect overview
                    for your application.
                </p>

                <div class="mt-4 flex flex-wrap gap-3">
                    <x-filament::button href="https://filamentphp.com/docs"
                                        tag="a"
                                        target="_blank"
                                        color="gray"
                                        icon="heroicon-o-book-open"
                                        size="sm"
                                        outlined>
                        Filament Overview
                    </x-filament::button>

                    <x-filament::button href="https://filamentphp.com/docs/widgets"
                                        tag="a"
                                        target="_blank"
                                        color="gray"
                                        icon="heroicon-o-square-3-stack-3d"
                                        size="sm"
                                        outlined>
                        Filament Widgets
                    </x-filament::button>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
