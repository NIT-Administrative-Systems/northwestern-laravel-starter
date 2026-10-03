{{--
    The Northwestern header for pages outside the panels' full layout: public pages, the
    sign-in and lockdown pages, and error pages. It matches the panels' top bar (see
    <x-panel-brand>): the wordmark, the application name, then whatever the page puts in
    the slot, such as the Help menu and Sign in or the user menu.

    Error pages use it, so it must render without Filament, auth or the database.
--}}
<header class="bg-nu-purple-120 w-full text-white">
    <div class="flex h-16 items-center gap-4 px-4 md:px-6 lg:px-8">
        <a class="block shrink-0 [&_.nu-wordmark]:h-5 [&_.nu-wordmark]:w-auto [&_.nu-wordmark]:text-white"
           href="https://www.northwestern.edu/">
            @include('northwestern-filament-theme::wordmark')
        </a>

        <span class="hidden h-6 w-px shrink-0 bg-white/30 sm:block" aria-hidden="true"></span>

        <a class="font-nu-heading hidden min-w-0 truncate text-lg font-semibold text-white sm:block"
           href="{{ url('/') }}">
            {{ config('app.name') }}
        </a>

        @if ($slot->hasActualContent())
            <div class="ms-auto flex shrink-0 items-center gap-2 sm:gap-3">
                {{ $slot }}
            </div>
        @endif
    </div>

    {{-- On phones the name gets its own row, so the buttons beside the wordmark never squeeze it. --}}
    <div class="border-t border-white/15 px-4 py-3 sm:hidden">
        <a class="font-nu-heading block text-base font-semibold leading-snug text-white" href="{{ url('/') }}">
            {{ config('app.name') }}
        </a>
    </div>
</header>
