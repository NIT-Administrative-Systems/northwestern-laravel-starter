{{--
    One announcement in a banner, for the app panel, the public
    and sign-in pages, and for the preview in Administration.

    Only the app panel's can be dismissed or link to the Announcements page.

    Styled as a Filament callout without its heading, which is an
    <h4> and would skip heading levels above the page's <h1>.
--}}
@props([
    'announcement',
    // Other live announcements the person hasn't dismissed, counted in "N more".
    'others' => 0,
    // The dismiss button, which calls the surrounding Livewire component's dismiss().
    'dismissible' => false,
    // The Announcements page. Without it, the body shows in full and has no links.
    'pageUrl' => null,
])

@php
    use Filament\Support\Icons\Heroicon;

    /** @var \App\Domains\Support\Models\Announcement $announcement */
    $severity = $announcement->severity;
    $readMoreUrl = $pageUrl !== null ? "{$pageUrl}#announcement-{$announcement->getKey()}" : null;
@endphp

<x-filament::callout data-testid="announcement-banner"
                     role="region"
                     aria-label="Announcement"
                     :color="$severity->getColor()"
                     :icon="$severity->getIcon()">
    <x-slot name="footer">
        <div class="flex flex-col gap-1">
            {{-- Not .fi-callout-heading: the theme styles that as a small uppercase label. --}}
            <p class="font-nu-heading text-base font-semibold text-gray-950 dark:text-white">
                <span class="sr-only">{{ $severity->getLabel() }}:</span>
                {{ $announcement->title }}
            </p>

            <div @class(['fi-prose text-sm', 'line-clamp-2' => $readMoreUrl !== null])>
                {{ $announcement->bodyHtml() }}
            </div>

            @if ($readMoreUrl !== null)
                <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                    <x-filament::link :href="$readMoreUrl">Read More</x-filament::link>

                    @if ($others > 0)
                        <x-filament::link :href="$pageUrl" color="gray">
                            {{ $others }} More {{ str('Announcement')->plural($others) }}
                        </x-filament::link>
                    @endif
                </div>
            @endif
        </div>
    </x-slot>

    @if ($dismissible)
        <x-slot name="controls">
            <x-filament::icon-button data-testid="dismiss-announcement"
                                     :icon="Heroicon::XMark"
                                     color="gray"
                                     label="Dismiss Announcement"
                                     wire:click="dismiss({{ $announcement->getKey() }})" />
        </x-slot>
    @endif
</x-filament::callout>
