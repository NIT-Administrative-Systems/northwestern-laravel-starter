{{--
    The layout for pages outside the panels, such as the landing page and the changelog.

    Routes using it run in the app panel's context (the `panel:app` middleware), so the
    page loads the app panel's theme and colors and can use Filament's Blade components.
    It is light-only: Department Templates 4.0 has no dark mode.
--}}
@props([
    'title' => null,
    // Announcements for signed-out visitors, under the header. Error pages turn it off.
    'announcements' => true,
])

@php
    use App\Providers\Filament\AppPanelProvider;
    use Filament\Facades\Filament;
    use Filament\Livewire\SimpleUserMenu;

    $appName = config('app.name');
    $appPanel = Filament::getPanel(AppPanelProvider::ID);

    // Error pages render on this layout too, from wherever the error happened (an
    // administration page or an unknown URL), so the app panel is selected here. A
    // failing user lookup leaves the page signed out rather than failing it.
    Filament::setCurrentPanel($appPanel);
    Filament::bootCurrentPanel();
    $user = rescue(fn() => auth()->user(), null, report: false);
@endphp

<!DOCTYPE html>
<html class="fi min-h-screen" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ filled($title) ? "{$title} - {$appName}" : $appName }}</title>

    @if ($favicon = filament()->getFavicon())
        <link href="{{ $favicon }}" rel="icon">
    @endif

    <style>
        [x-cloak=''],
        [x-cloak='x-cloak'],
        [x-cloak='1'] {
            display: none !important;
        }
    </style>

    @livewireStyles
    @filamentStyles
    {{ filament()->getTheme()->getHtml() }}
    {{ filament()->getFontHtml() }}
    {{ filament()->getMonoFontHtml() }}
    {{ filament()->getSerifFontHtml() }}

    <style>
        /* As Filament's panel layout sets them. Without the mono and serif
           variables, `font-mono` and `font-serif` resolve to nothing and fall
           back to the body font. */
        :root {
            --font-family: '{!! filament()->getFontFamily() !!}';
            --mono-font-family: '{!! filament()->getMonoFontFamily() !!}';
            --serif-font-family: '{!! filament()->getSerifFontFamily() !!}';
        }
    </style>

    <x-sentry-browser />
</head>

{{-- White like the error layout, marked important because the theme's unlayered
     `body.fi-body` sets a gray background. --}}
<body class="fi-body bg-white! flex min-h-screen flex-col text-gray-950 antialiased">
    <a class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:bg-white focus:px-4 focus:py-2"
       href="#main">
        Skip to Content
    </a>

    <x-site-header>
        <x-help-menu />

        @if ($user)
            @livewire(SimpleUserMenu::class)
        @else
            <x-filament::button class="whitespace-nowrap"
                                data-testid="sign-in-link"
                                tag="a"
                                :href="$appPanel->getLoginUrl()"
                                color="gray">
                Sign In
            </x-filament::button>
        @endif
    </x-site-header>

    @if ($announcements)
        <x-public-announcement-banner />
    @endif

    <main class="flex-1" id="main">
        {{ $slot }}
    </main>

    <x-northwestern-filament-theme::footer />

    {{-- Livewire's scripts bring Alpine, which Filament's components need. Livewire
         adds them by itself only to pages that render a Livewire component, and
         these pages render none. --}}
    @livewireScripts
    {{-- Without the panel core, which would apply the person's theme and dark mode. --}}
    @filamentScripts
</body>

</html>
