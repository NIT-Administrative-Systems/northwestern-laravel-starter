<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ $returning ? 'Welcome Back' : 'Welcome' }}, {{ $firstName }}
        </x-slot>

        <x-slot name="afterHeader">
            <x-filament::link :href="$accountUrl" icon="heroicon-o-user-circle">
                Account
            </x-filament::link>
        </x-slot>

        <dl class="grid gap-6 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-950 dark:text-white">Roles</dt>
                <dd class="mt-2">
                    @if ($roles === [])
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            None beyond standard access. An administrator can assign roles.
                        </span>
                    @else
                        <div class="flex flex-wrap gap-2">
                            @foreach ($roles as $role)
                                <x-filament::badge>{{ $role }}</x-filament::badge>
                            @endforeach
                        </div>
                    @endif
                </dd>
            </div>

            @if ($contactSupportUrl || $documentationUrl)
                <div>
                    <dt class="text-sm font-medium text-gray-950 dark:text-white">Need Help?</dt>
                    <dd class="mt-2 flex flex-wrap gap-x-6 gap-y-2">
                        @if ($contactSupportUrl)
                            <x-filament::link :href="$contactSupportUrl" icon="heroicon-o-lifebuoy">
                                Contact Support
                            </x-filament::link>
                        @endif

                        @if ($documentationUrl)
                            <x-filament::link :href="$documentationUrl"
                                              icon="heroicon-o-book-open"
                                              target="_blank"
                                              rel="noopener">
                                Documentation
                            </x-filament::link>
                        @endif
                    </dd>
                </div>
            @endif
        </dl>
    </x-filament::section>
</x-filament-widgets::widget>
