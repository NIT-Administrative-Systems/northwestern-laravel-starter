{{--
    The footer of every markdown email: the responsible unit's contact details, the
    Accessibility and Privacy Statement links the university requires, and the copyright.
    Rendered as Markdown for HTML mail and as plain text for the text part.

    Unit details resolve through the filament theme's FooterConfig, the same as the
    page footer: config/northwestern-filament-theme.php, then the built-in defaults.
--}}
@props(['plain' => false])

@php
    use Northwestern\FilamentTheme\Footer\FooterConfig;
    use Northwestern\FilamentTheme\Footer\RequiredLink;

    $office = (new FooterConfig())->office();

    $unit = array_filter([
        $office['name'],
        collect([$office['addr'], $office['city']])->filter()->implode(', '),
        $office['phone'],
        filled($office['fax']) ? "Fax {$office['fax']}" : null,
        $office['email'],
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
