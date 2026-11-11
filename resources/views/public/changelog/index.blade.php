<x-layouts.public title="Changelog">
    <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="font-nu-heading text-nu-purple-100 text-4xl font-bold">Changelog</h1>

            {{-- The feed opens in the browser or a feed reader, and Copy puts
                 its address on the clipboard for readers that ask for it.

                 The tooltip shows the result, and the status region announces it. --}}
            <div class="flex items-center gap-2" x-data="{
                status: '',
                copy() {
                    navigator.clipboard.writeText({{ Js::from($feedUrl) }})
                        .then(() => this.announce('Feed URL copied'))
                        .catch(() => this.announce('Couldn\'t copy the feed URL'));
                },
                announce(message) {
                    this.status = '';
                    this.$nextTick(() => this.status = message);
                    this.$tooltip(message, { timeout: 2000 });
                },
            }">
                <x-filament::button class="pointer-coarse:min-h-11"
                                    data-testid="changelog-feed"
                                    tag="a"
                                    :href="$feedUrl"
                                    color="gray"
                                    outlined
                                    size="sm"
                                    icon="heroicon-m-rss">
                    RSS Feed
                </x-filament::button>
                <x-filament::button class="pointer-coarse:min-h-11"
                                    data-testid="changelog-feed-copy"
                                    aria-label="Copy Feed URL"
                                    color="gray"
                                    outlined
                                    size="sm"
                                    icon="heroicon-m-clipboard-document"
                                    x-on:click="copy()">
                    Copy
                </x-filament::button>
                <span class="sr-only"
                      role="status"
                      x-text="status"></span>
            </div>
        </div>

        @if ($entries->isEmpty())
            <p class="mt-10 text-gray-600">No changelog entries yet.</p>
        @else
            <div class="divide-y divide-gray-200">
                @foreach ($entries as $entry)
                    <article class="grid gap-x-8 gap-y-2 py-10 md:grid-cols-[10rem_1fr]" id="{{ $entry->slug }}">
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

                            {{-- Section headings rank below the entry's title, and list bullets are visible. --}}
                            <div
                                 class="fi-prose [--prose-marker-color:var(--color-gray-400)]! [&_h3]:text-base! [&_h3]:font-semibold! [&_h3]:text-gray-600! mt-4 [&_h3]:uppercase [&_h3]:tracking-wide">
                                {{ $entry->bodyHtml(topHeadingLevel: 3) }}
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        <div class="mt-8">
            {{ $entries->links('pagination::tailwind') }}
        </div>
    </div>
</x-layouts.public>
