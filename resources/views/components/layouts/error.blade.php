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
<html class="min-h-screen" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">

    <title>{{ $title }} - {{ $appName }}</title>

    <link href="https://common.northwestern.edu/favicon.ico" rel="icon">

    @vite('resources/css/errors.css')
    <x-sentry-browser />
</head>

<body class="font-nu-body flex min-h-screen flex-col bg-white text-gray-900 antialiased">
    <x-site-header />

    <main class="flex-1">
        {{ $slot }}
    </main>

    <x-northwestern-filament-theme::footer />

    @stack('scripts')
</body>

</html>
