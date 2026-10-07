<x-layouts.public :title="$entry->title ?? $entry->slug">
    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
        <x-filament::link :href="route('support.changelog.index')" icon="heroicon-m-arrow-left">
            Changelog
        </x-filament::link>

        <div class="mt-6 flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 pb-6">
            <div>
                <time class="text-sm font-semibold text-gray-500"
                      datetime="{{ $entry->created_at->toDateString() }}">{{ $entry->created_at->format('F j, Y') }}</time>
                <h1 class="font-nu-heading text-nu-purple-100 mt-2 text-3xl font-bold">
                    {{ $entry->title ?? $entry->slug }}
                </h1>
            </div>

            {{-- The device's share sheet where there is one (phones, Safari); elsewhere it copies the link,
                 shown in a tooltip and announced to screen readers through the status region. --}}
            <div x-data="{
                url: {{ Js::from(route('support.changelog.show', $entry)) }},
                title: {{ Js::from($entry->title ?? $entry->slug) }},
                status: '',
                share() {
                    if (navigator.share) {
                        navigator.share({ title: this.title, url: this.url }).catch(() => {});
            
                        return;
                    }
            
                    navigator.clipboard.writeText(this.url)
                        .then(() => this.announce('Link copied'))
                        .catch(() => this.announce('Couldn\'t copy the link'));
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
                                    icon="heroicon-m-share"
                                    x-on:click="share()">
                    Share
                </x-filament::button>
                <span class="sr-only"
                      role="status"
                      x-text="status"></span>
            </div>
        </div>

        <div class="fi-prose mt-6">
            {{ $entry->bodyHtml(topHeadingLevel: 2) }}
        </div>
    </div>
</x-layouts.public>
