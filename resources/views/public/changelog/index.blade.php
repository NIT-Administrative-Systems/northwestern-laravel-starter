<x-layouts.public title="Changelog">
    <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="font-nu-heading text-3xl font-bold text-gray-950">Changelog</h1>

            <x-filament::button color="gray"
                                outlined
                                size="sm"
                                icon="heroicon-m-rss"
                                x-data="{}"
                                x-on:click="window.navigator.clipboard.writeText({{ Js::from($feedUrl) }}).then(() => $tooltip('Feed URL copied', { timeout: 2000 })).catch(() => $tooltip('Unable to copy', { timeout: 2000 }))">
                RSS Feed
            </x-filament::button>
        </div>

        @forelse ($entries as $entry)
            <article class="grid gap-x-8 gap-y-2 border-b border-gray-200 py-10 last:border-b-0 md:grid-cols-[10rem_1fr]"
                     id="{{ $entry->slug }}">
                <div class="text-sm font-semibold text-gray-500 md:sticky md:top-6 md:self-start md:text-right">
                    <a class="hover:text-nu-purple-100" href="{{ route('support.changelog.show', $entry) }}">
                        <time
                              datetime="{{ $entry->created_at->toDateString() }}">{{ $entry->created_at->format('F j, Y') }}</time>
                    </a>
                </div>

                <div>
                    <h2 class="font-nu-heading text-2xl font-bold">
                        <a class="hover:text-nu-purple-100 text-gray-950"
                           href="{{ route('support.changelog.show', $entry) }}">
                            {{ $entry->title ?? $entry->slug }}
                        </a>
                    </h2>

                    <div class="fi-prose mt-4">
                        <x-markdown :anchors="false" :options="['html_input' => 'escape']">
                            {!! $entry->body !!}
                        </x-markdown>
                    </div>
                </div>
            </article>
        @empty
            <p class="mt-10 text-gray-600">No changelog entries yet.</p>
        @endforelse

        <div class="mt-8">
            {{ $entries->links('pagination::tailwind') }}
        </div>
    </div>
</x-layouts.public>
