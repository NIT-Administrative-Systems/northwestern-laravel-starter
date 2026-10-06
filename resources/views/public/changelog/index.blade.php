<x-layouts.public title="Changelog">
    <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="font-nu-heading text-nu-purple-100 text-3xl font-bold">Changelog</h1>

            {{-- The tooltip shows the result; the status region announces it to screen readers. --}}
            <div x-data="{
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
                <x-filament::button color="gray"
                                    outlined
                                    size="sm"
                                    icon="heroicon-m-rss"
                                    x-on:click="copy()">
                    RSS Feed
                </x-filament::button>
                <span class="sr-only"
                      role="status"
                      x-text="status"></span>
            </div>
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
                        {{ $entry->bodyHtml(topHeadingLevel: 3) }}
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
