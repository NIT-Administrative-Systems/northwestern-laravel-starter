{{--
    The body shared by the simple error pages: a heading, the message in the slot, and a way home.

    Client errors (401, 403, 404 and so on) render on the public layout, so the header keeps the
    Help menu and Sign in or the user menu. Pages passing :navigation="false" use the bare error
    layout, which renders without Filament, auth or the database.
--}}
<x-dynamic-component :component="$navigation ? 'layouts.public' : 'layouts.error'" :title="$title">
    <section class="mx-auto max-w-2xl px-4 py-20 text-center sm:px-6 lg:py-28">
        <h1 class="font-nu-heading text-nu-purple-100 text-4xl font-bold tracking-tight">{{ $title }}</h1>

        <p class="mt-6 text-lg text-gray-600">{{ $slot }}</p>

        <a class="bg-nu-purple-100 hover:bg-nu-purple-120 focus-visible:outline-nu-purple-100 mt-10 inline-flex items-center px-5 py-3 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2"
           href="{{ url('/') }}">
            Back to homepage
        </a>
    </section>
</x-dynamic-component>
