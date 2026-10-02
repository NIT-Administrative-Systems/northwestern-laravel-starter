{{--
    The layout for error pages, including the 500 and database-paused pages.

    It must render when nothing else works: no Filament, no auth and no database. Keep
    anything that could query the database out of it, and guard it in the pages that
    need it. It is light-only, like the public layout.
--}}
@props([
    'title' => 'Error',
])

@php
    $appName = config('app.name');
@endphp

<!DOCTYPE html>
<html class="min-h-screen"
      lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">

    <title>{{ $title }} - {{ $appName }}</title>

    <link rel="icon" href="https://common.northwestern.edu/favicon.ico">

    @vite('resources/css/errors.css')
    <x-sentry-browser />
</head>

<body class="font-nu-body flex min-h-screen flex-col bg-white text-gray-900 antialiased">
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
            <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
                <a class="font-nu-heading text-nu-purple-100 text-lg font-bold sm:text-xl"
                   href="{{ url('/') }}">
                    {{ $appName }}
                </a>
            </div>
        </div>
    </header>

    <main class="flex-1">
        {{ $slot }}
    </main>

    <x-northwestern-filament-theme::footer />

    @stack('scripts')
</body>

</html>
