{{--
    The Northwestern header for pages outside the panels' full layout:
    public pages, the sign-in and lockdown pages, and error pages.

    It matches the panels' top bar (see <x-panel-brand>): the wordmark, or
    the unit's lockup when NU_LOCKUP is set, the application name, then
    the slot, which holds the Help menu and Sign In or the user menu.

    Above it, the theme's impersonation banner, with Leave Impersonation, shows
    while someone is impersonating, as the panels show it above the top bar.

    Error pages use it, so it must render without Filament,
    auth or the database. The lockup reads only config.

    The banner reads the session and the signed-in user, so it
    is left out if those fail rather than failing the page.

    Below the lg breakpoint the application name moves to a row of
    its own, so the controls by the wordmark never squeeze it.

    Outside production it shows the environment badge, as the panels' top bar does:
    top right from sm, and with a short label at the name row's end on a phone.

    The theme's defaults decide when the badge shows and its label.

    The gold rule runs along the top of the header, where the theme draws it
    on the panels' top bar, so it stays put between sign-in and the app.

    From the lg breakpoint the row lines up with the footer's 70rem container.
--}}
@php
    use Illuminate\Support\Facades\Blade;
    use Northwestern\FilamentTheme\EnvironmentIndicator\EnvironmentIndicatorConfig;
    use Northwestern\FilamentTheme\NorthwesternTheme;

    $environment = new EnvironmentIndicatorConfig();
    $showEnvironment = $environment->isVisible();
    $hasLockup = NorthwesternTheme::lockupUrl() !== null;

    // Its defaults (lab404's session, impersonate.leave) match the panels' banner.
    $impersonationBanner = rescue(
        fn(): string => Blade::render('<x-northwestern-filament-theme::impersonation-banner />'),
        '',
        report: false,
    );
@endphp

{!! $impersonationBanner !!}

<header @class([
    'nu-site-header nu-focus-on-purple bg-nu-purple-120 w-full text-white',
    'border-t-4 border-[var(--nu-gold)]' => $showEnvironment,
])>
    {{-- 4rem tall in all, like the panels' top bar,
         which fits the rule inside that height.

         The 72rem row less its 1rem padding is the footer's 70rem container. --}}
    <div @class([
        'mx-auto flex w-full items-center gap-4 px-4 md:px-6 lg:max-w-6xl lg:px-4',
        'h-16' => !$showEnvironment,
        'h-15' => $showEnvironment,
    ])>
        @if ($hasLockup)
            {{-- The white lockup at the panels' logo height, with the brand's clear space. --}}
            <x-northwestern-filament-theme::lockup data-testid="site-header-lockup"
                                                   href="https://www.northwestern.edu/" />
        @else
            <a class="block shrink-0 [&_.nu-wordmark]:h-5 [&_.nu-wordmark]:w-auto [&_.nu-wordmark]:-translate-y-[10%] [&_.nu-wordmark]:text-white sm:[&_.nu-wordmark]:h-6"
               href="https://www.northwestern.edu/">
                @include('northwestern-filament-theme::wordmark')
            </a>
        @endif

        <span class="hidden h-6 w-px shrink-0 bg-white/30 lg:block" aria-hidden="true"></span>

        <a class="font-nu-heading hidden min-w-0 truncate text-lg font-semibold text-white lg:block"
           href="{{ url('/') }}">
            {{ config('app.name') }}
        </a>

        @if ($showEnvironment || $slot->hasActualContent())
            <div class="ms-auto flex shrink-0 items-center gap-2 sm:gap-3">
                @if ($showEnvironment)
                    {{-- The badge hides itself below the sm breakpoint; the name row shows it there. --}}
                    @include('northwestern-filament-theme::environment-indicator', [
                        'config' => $environment,
                        'placement' => 'topbar',
                    ])
                @endif

                {{ $slot }}
            </div>
        @endif
    </div>

    <div class="flex items-center gap-3 border-t border-white/15 px-4 py-3 md:px-6 lg:hidden">
        <a class="font-nu-heading min-w-0 flex-1 text-base font-semibold leading-snug text-white"
           href="{{ url('/') }}">
            {{ config('app.name') }}
        </a>

        @if ($showEnvironment)
            <div class="nu-site-header-environment shrink-0 sm:hidden">
                @include('northwestern-filament-theme::environment-indicator', [
                    'config' => $environment,
                    'placement' => 'topbar',
                ])
            </div>
        @endif
    </div>
</header>

@if ($showEnvironment)
    <style>
        .nu-site-header-environment .nu-env-indicator {
            display: inline-flex;
        }
    </style>
@endif
