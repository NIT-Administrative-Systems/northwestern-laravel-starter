{{--
    The body every error page shares: the status code, a heading, one sentence in
    the slot, plus the next step for that status, from ErrorLayout::action().

    The `help` slot adds centered text under the sentence, such as who to contact,
    and `details` adds wider content below, such as the 500 page's report form.

    Client errors (401, 403, 404 and so on) render on the public layout,
    so the header keeps the Help menu, and Sign In or the user menu.

    Pages passing :navigation="false" use the bare error layout,
    which can render without Filament, auth or the database.

    The app theme and errors.css both compile this view, so
    the page and its button look alike on either layout.
--}}
<x-dynamic-component :component="$navigation ? 'layouts.public' : 'layouts.error'"
                     :title="$title"
                     :announcements="false">
    <section class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:py-24">
        <div class="mx-auto max-w-2xl text-center">
            @if ($code)
                <p class="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-600">Error {{ $code }}
                </p>
            @endif

            <h1 class="font-nu-heading text-nu-purple-100 text-4xl font-bold tracking-tight">{{ $title }}</h1>

            <p class="mt-6 text-lg text-gray-600">{{ $slot }}</p>

            {{ $help ?? '' }}

            @if ($action)
                {{-- Like Filament's large primary button, which the bare layout doesn't load. --}}
                <a class="bg-nu-purple-100 hover:bg-nu-purple-120 focus-visible:outline-nu-purple-100 pointer-coarse:min-h-11 mt-10 inline-flex items-center justify-center px-3.5 py-2.5 text-sm font-medium text-white transition focus-visible:outline-2 focus-visible:outline-offset-2"
                   data-testid="error-action"
                   href="{{ $action['url'] }}">
                    {{ $action['label'] }}
                </a>
            @endif
        </div>

        {{ $details ?? '' }}
    </section>
</x-dynamic-component>
