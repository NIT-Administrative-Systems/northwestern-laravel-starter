{{--
    The footer of every markdown email: the responsible unit's contact details, the
    Accessibility and Privacy Statement links the university requires, and the copyright.
    Rendered as Markdown for HTML mail and as plain text for the text part.

    Unit details come from config('northwestern-theme.office.*') until the starter
    adopts the filament theme's own config.
--}}
@props(['plain' => false])

@php
    use Northwestern\FilamentTheme\Footer\RequiredLink;

    $unit = array_filter([
        config('northwestern-theme.office.name'),
        collect([config('northwestern-theme.office.addr'), config('northwestern-theme.office.city')])->filter()->implode(', '),
        config('northwestern-theme.office.phone'),
        config('northwestern-theme.office.email'),
    ]);

    $links = [RequiredLink::Accessibility, RequiredLink::PrivacyStatement];
@endphp

@if ($plain)
{{ implode(' | ', $unit) }}

@foreach ($links as $link)
{{ $link->label() }}: {{ $link->url() }}
@endforeach

© {{ date('Y') }} Northwestern University. @lang('All rights reserved.')
@else
{{ implode(' · ', $unit) }}

{!! collect($links)->map(fn (RequiredLink $link): string => "[{$link->label()}]({$link->url()})")->implode(' · ') !!}

© {{ date('Y') }} Northwestern University. @lang('All rights reserved.')
@endif
