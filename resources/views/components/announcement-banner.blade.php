{{--
    One announcement in a banner: the app panel's (with dismissing and links to the
    Announcements page), the public pages' and sign-in pages' (without), and the preview in
    Administration. Styled as a Filament callout, without its heading: a callout heading is an
    <h4>, which would skip heading levels above the page's <h1>.
--}}
@props([
    'announcement',
    // Other live announcements the person hasn't dismissed, counted in "N more".
    'others' => 0,
    // Show the dismiss button, which calls the surrounding Livewire component's dismiss().
    'dismissible' => false,
    // The Announcements page; without it (signed-out visitors), the body isn't clamped and there are no page links.
    'pageUrl' => null,
])

@php
    use Filament\Support\Icons\Heroicon;

    /** @var \App\Domains\Support\Models\Announcement $announcement */
    $severity = $announcement->severity;
    $readMoreUrl = $pageUrl !== null ? "{$pageUrl}#announcement-{$announcement->getKey()}" : null;
@endphp

<x-filament::callout data-cy="announcement-banner"
                     role="region"
                     aria-label="Announcement"
                     :color="$severity->getColor()"
                     :icon="$severity->getIcon()">
    <x-slot name="footer">
        <div class="flex flex-col gap-1">
            <p class="fi-callout-heading">
                <span class="sr-only">{{ $severity->getLabel() }}:</span>
                {{ $announcement->title }}
            </p>

            <div @class(['fi-prose text-sm', 'line-clamp-2' => $readMoreUrl !== null])>
                {{ $announcement->bodyHtml() }}
            </div>

            @if ($readMoreUrl !== null)
                <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                    <x-filament::link :href="$readMoreUrl">Read more</x-filament::link>

                    @if ($others > 0)
                        <x-filament::link :href="$pageUrl" color="gray">
                            {{ $others }} more {{ str('announcement')->plural($others) }}
                        </x-filament::link>
                    @endif
                </div>
            @endif
        </div>
    </x-slot>

    @if ($dismissible)
        <x-slot name="controls">
            <x-filament::icon-button data-cy="dismiss-announcement"
                                     :icon="Heroicon::XMark"
                                     color="gray"
                                     label="Dismiss announcement"
                                     wire:click="dismiss({{ $announcement->getKey() }})" />
        </x-slot>
    @endif
</x-filament::callout>
