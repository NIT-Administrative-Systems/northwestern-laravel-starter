<x-layouts.public :title="$entry->title ?? $entry->slug">
    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
        <x-filament::link :href="route('support.changelog.index')" icon="heroicon-m-arrow-left">
            Changelog
        </x-filament::link>

        <div class="mt-6 flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 pb-6">
            <div>
                <time class="text-sm font-semibold text-gray-500"
                      datetime="{{ $entry->created_at->toDateString() }}">{{ $entry->created_at->format('F j, Y') }}</time>
                <h1 class="font-nu-heading mt-2 text-3xl font-bold text-gray-950">
                    {{ $entry->title ?? $entry->slug }}
                </h1>
            </div>

            {{-- The device's share sheet where there is one (phones, Safari); elsewhere it copies the link. --}}
            <x-filament::button color="gray"
                                outlined
                                size="sm"
                                icon="heroicon-m-share"
                                x-data="{
                                    url: {{ Js::from(route('support.changelog.show', $entry)) }},
                                    title: {{ Js::from($entry->title ?? $entry->slug) }},
                                    share() {
                                        if (navigator.share) {
                                            navigator.share({ title: this.title, url: this.url }).catch(() => {});
                                
                                            return;
                                        }
                                
                                        navigator.clipboard.writeText(this.url)
                                            .then(() => this.$tooltip('Link copied', { timeout: 2000 }))
                                            .catch(() => this.$tooltip('Unable to copy', { timeout: 2000 }));
                                    },
                                }"
                                x-on:click="share()">
                Share
            </x-filament::button>
        </div>

        <div class="fi-prose mt-6">
            <x-markdown :anchors="false" :options="['html_input' => 'escape']">
                {!! $entry->body !!}
            </x-markdown>
        </div>
    </div>
</x-layouts.public>
