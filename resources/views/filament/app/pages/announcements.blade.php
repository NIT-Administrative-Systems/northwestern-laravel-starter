@php
    use App\Domains\Support\Enums\AnnouncementStatus;
    use Filament\Support\Icons\Heroicon;

    $announcements = $this->getAnnouncements();
    $timezone = auth()->user()->timezone ?: config('app.timezone');
@endphp

<x-filament-panels::page>
    @if ($announcements->isEmpty())
        <x-filament::empty-state :icon="Heroicon::OutlinedMegaphone" heading="No Announcements">
            Announcements for you appear here.
        </x-filament::empty-state>
    @else
        <ol class="flex flex-col gap-4" aria-label="Announcements">
            @foreach ($announcements as $announcement)
                @php($live = $announcement->status === AnnouncementStatus::Live)
                <li class="scroll-mt-24" id="announcement-{{ $announcement->getKey() }}">
                    <x-filament::section :icon="$announcement->severity->getIcon()" :icon-color="$announcement->severity->getColor()">
                        <x-slot name="heading">
                            <span class="sr-only">{{ $announcement->severity->getLabel() }}:</span>
                            {{ $announcement->title }}
                        </x-slot>

                        <x-slot name="description">
                            <time datetime="{{ $announcement->starts_at?->toIso8601String() }}">
                                {{ $announcement->starts_at?->timezone($timezone)->format('F j, Y') }}
                            </time>
                            @if ($live && $announcement->ends_at)
                                · Until
                                {{ \App\Domains\Core\Formatting\NorthwesternDateTime::format($announcement->ends_at, $timezone, withZone: false) }}
                            @elseif (!$live)
                                · Ended {{ $announcement->ends_at?->timezone($timezone)->format('F j, Y') }}
                            @endif
                        </x-slot>

                        @if ($live)
                            <x-slot name="afterHeader">
                                <x-filament::badge color="success">Current</x-filament::badge>
                            </x-slot>
                        @endif

                        <div class="fi-prose">
                            {{ $announcement->bodyHtml() }}
                        </div>
                    </x-filament::section>
                </li>
            @endforeach
        </ol>

        <x-filament::pagination :paginator="$announcements" />
    @endif
</x-filament-panels::page>
