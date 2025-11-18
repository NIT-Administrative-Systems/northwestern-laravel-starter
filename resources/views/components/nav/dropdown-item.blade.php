@props([
    'href' => '#',
    'icon' => null,
    'external' => null,
    'disabled' => false,
])

<li>
    <a href="{{ $href }}"
       @class(['dropdown-item', 'disabled' => $disabled])
       {{ $attributes }}
       @if ($disabled) aria-disabled="true"
       tabindex="-1" @endif
       @if ($external) target="_blank" @endif>
        @if ($icon)
            <i class="fas {{ $icon }} fa-fw me-1" aria-hidden="true"></i>
        @endif
        {{ $slot }}
    </a>
</li>
