{{--
    The Northwestern header for pages outside the panels' full layout: public pages, the
    sign-in and lockdown pages, and error pages. It matches the panels' top bar (see
    <x-panel-brand>): the wordmark, the application name, then whatever the page puts in
    the slot, such as the Help menu and Sign in or the user menu.

    Error pages use it, so it must render without Filament, auth or the database.

    Outside production it shows the environment badge, as the panels' top bar does: top
    right on larger screens, centered below the header on phones. The gold rule runs along
    the top of the header, where the theme draws it on the panels' top bar, so it stays in
    place between signing in and the app. The theme's default settings decide when it shows
    and its label.
--}}
@php
    use Northwestern\FilamentTheme\EnvironmentIndicator\EnvironmentIndicatorConfig;

    $environment = new EnvironmentIndicatorConfig();
    $showEnvironment = $environment->isVisible();
@endphp

<header @class([
    'nu-site-header bg-nu-purple-120 w-full text-white',
    // The theme's top bar uses #A76616 for the rule in dark mode; the sign-in pages follow dark mode.
    'border-t-4 border-[var(--nu-gold)] dark:border-[#A76616]' => $showEnvironment,
])>
    {{-- 4rem tall in all, like the panels' top bar, which fits the rule inside that height. --}}
    <div @class([
        'flex items-center gap-4 px-4 md:px-6 lg:px-8',
        'h-16' => !$showEnvironment,
        'h-15' => $showEnvironment,
    ])>
        <a class="block shrink-0 [&_.nu-wordmark]:h-5 [&_.nu-wordmark]:w-auto [&_.nu-wordmark]:-translate-y-[10%] [&_.nu-wordmark]:text-white sm:[&_.nu-wordmark]:h-6"
           href="https://www.northwestern.edu/">
            @include('northwestern-filament-theme::wordmark')
        </a>

        <span class="hidden h-6 w-px shrink-0 bg-white/30 sm:block" aria-hidden="true"></span>

        <a class="font-nu-heading hidden min-w-0 truncate text-lg font-semibold text-white sm:block"
           href="{{ url('/') }}">
            {{ config('app.name') }}
        </a>

        @if ($showEnvironment || $slot->hasActualContent())
            <div class="ms-auto flex shrink-0 items-center gap-2 sm:gap-3">
                @if ($showEnvironment)
                    {{-- The badge hides itself below the sm breakpoint; the row under the header takes over. --}}
                    @include('northwestern-filament-theme::environment-indicator', [
                        'config' => $environment,
                        'placement' => 'topbar',
                    ])
                @endif

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

@if ($showEnvironment)
    <div class="nu-site-header-environment flex justify-center px-4 pt-4 sm:hidden">
        @include('northwestern-filament-theme::environment-indicator', [
            'config' => $environment,
            'placement' => 'topbar',
        ])
    </div>

    <style>
        .nu-site-header-environment .nu-env-indicator {
            display: inline-flex;
        }
    </style>
@endif
