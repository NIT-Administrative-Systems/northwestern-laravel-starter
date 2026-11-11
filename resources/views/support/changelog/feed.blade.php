<?php
// Echoed, so PHP with short open tags doesn't read the declaration as code.
// {{-- prettier-ignore --}}
echo '<?xml version="1.0" encoding="UTF-8"?>';

$title = config('app.name') . ' Changelog';
if (!app()->environment('production')) {
    $title .= sprintf(' (%s)', config('app.env'));
}
?>
<rss version="2.0"
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:content="http://purl.org/rss/1.0/modules/content/">
    <channel>
        <atom:link type="application/rss+xml"
                   href="{{ route('support.changelog.feed') }}"
                   rel="self" />
        <title>{{ $title }}</title>
        <link>{{ route('support.changelog.index') }}</link>
        <description>Platform release notes and updates.</description>
        <language>en</language>
        <copyright>{{ date('Y') }} {{ config('app.name') }}</copyright>
        <pubDate>{{ ($entries->first()?->created_at ?? now())->toRssString() }}</pubDate>
        <lastBuildDate>{{ now()->toRssString() }}</lastBuildDate>
        <generator>{{ config('app.name') }}</generator>
        <docs>https://www.rssboard.org/rss-specification</docs>

        @foreach ($entries as $entry)
            <item>
                <title>{{ $entry->title ?? $entry->slug }}</title>
                <link>{{ route('support.changelog.show', $entry) }}</link>
                <description>
                    <![CDATA[<x-markdown :anchors="false" :options="['html_input' => 'escape']">{!! $entry->body !!}</x-markdown>]]>
                </description>
                <guid isPermaLink="true">{{ route('support.changelog.show', $entry) }}</guid>
                <pubDate>{{ $entry->created_at->toRssString() }}</pubDate>
            </item>
        @endforeach
    </channel>
</rss>
