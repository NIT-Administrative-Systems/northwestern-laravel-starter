{{--
    Rendered by App\View\Components\HelpMenu, which decides the items. Each item
    links to a page in this application, or opens the documentation in a new tab.
--}}
@php
    use Filament\Support\Icons\Heroicon;
@endphp

@if ($items !== [])
    <x-filament::dropdown data-testid="help-menu"
                          placement="bottom-end"
                          teleport>
        <x-slot name="trigger">
            {{-- Its focus ring is the header's, set in the panel themes. --}}
            <button class="flex h-9 items-center gap-1.5 rounded-lg px-2 text-sm font-medium text-white transition hover:bg-white/10 sm:px-3"
                    data-testid="help-menu-trigger"
                    type="button"
                    aria-label="Help">
                <x-filament::icon class="size-5" :icon="Heroicon::OutlinedQuestionMarkCircle" />
                <span class="hidden sm:inline">Help</span>
                <x-filament::icon class="hidden size-4 sm:block" :icon="Heroicon::ChevronDown" />
            </button>
        </x-slot>

        <x-filament::dropdown.list>
            @foreach ($items as $item)
                <x-filament::dropdown.list.item tag="a"
                                                :href="$item['url']"
                                                :icon="$item['icon']"
                                                :target="$item['external'] ? '_blank' : null">
                    {{ $item['label'] }}
                </x-filament::dropdown.list.item>
            @endforeach
        </x-filament::dropdown.list>
    </x-filament::dropdown>
@endif
