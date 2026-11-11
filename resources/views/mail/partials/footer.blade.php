{{--
    The footer of every Markdown email.

    It has the unit's contact details, the Accessibility and Privacy
    Statement links the university requires, and a copyright line.

    It renders as Markdown for HTML mail and as plain text for the text part.

    The unit's details come from the theme's FooterConfig, as the page footer's
    do: config/northwestern-filament-theme.php, then the built-in defaults.
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
