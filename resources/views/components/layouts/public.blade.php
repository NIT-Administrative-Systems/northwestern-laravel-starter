{{--
    The layout for pages outside the panels, such as the landing page and the changelog.

    Routes using it run in the app panel's context (the `panel:app` middleware), so the
    page loads the app panel's theme and colors and can use Filament's Blade components.
    It is light-only: Department Templates 4.0 has no dark mode.
--}}
@props([
    'title' => null,
])

@php
    use App\Providers\Filament\AppPanelProvider;
    use Filament\Facades\Filament;

    $appName = config('app.name');
    $appPanel = Filament::getPanel(AppPanelProvider::ID);
    $user = auth()->user();
@endphp

<!DOCTYPE html>
<html class="fi min-h-screen"
      lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ filled($title) ? "{$title} - {$appName}" : $appName }}</title>

    @if ($favicon = filament()->getFavicon())
        <link rel="icon" href="{{ $favicon }}">
    @endif

    <style>
        [x-cloak=''],
        [x-cloak='x-cloak'],
        [x-cloak='1'] {
            display: none !important;
        }
    </style>

    @filamentStyles
    {{ filament()->getTheme()->getHtml() }}
    {{ filament()->getFontHtml() }}

    <style>
        :root {
            --font-family: '{!! filament()->getFontFamily() !!}';
        }
    </style>

    <x-sentry-browser />
</head>

<body class="fi-body flex min-h-screen flex-col bg-white text-gray-950 antialiased">
    <a class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:bg-white focus:px-4 focus:py-2"
       href="#main">
        Skip to content
    </a>

    <header>
        <div class="bg-nu-purple-120">
            <div class="mx-auto flex h-12 max-w-7xl items-center px-4 sm:px-6 lg:px-8">
                <a class="block [&_.nu-wordmark]:h-5 [&_.nu-wordmark]:w-auto [&_.nu-wordmark]:text-white"
                   href="https://www.northwestern.edu/">
                    @include('northwestern-filament-theme::wordmark')
                </a>
            </div>
        </div>

        <div class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <a class="font-nu-heading text-nu-purple-100 text-lg font-bold sm:text-xl"
                   href="{{ url('/') }}">
                    {{ $appName }}
                </a>

                @if ($user)
                    <div class="flex items-center gap-3">
                        <span class="hidden text-sm text-gray-600 sm:inline">{{ $user->getFilamentName() }}</span>
                        <x-filament::button tag="a"
                                            :href="$appPanel->getUrl()"
                                            color="gray"
                                            outlined
                                            data-cy="back-to-app-link">
                            Back to app
                        </x-filament::button>
                    </div>
                @else
                    <x-filament::button tag="a"
                                        :href="$appPanel->getLoginUrl()"
                                        data-cy="sign-in-link">
                        Sign in
                    </x-filament::button>
                @endif
            </div>
        </div>
    </header>

    <main class="flex-1"
          id="main">
        {{ $slot }}
    </main>

    <x-northwestern-filament-theme::footer />

    {{-- Without the panel core, which would apply the user's panel theme (including dark mode). --}}
    @filamentScripts
</body>

</html>
