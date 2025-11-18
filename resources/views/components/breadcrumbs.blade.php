@php
    use Illuminate\Support\Facades\URL;
@endphp

<nav aria-label="breadcrumb" {{ $attributes->merge(['class' => 'ps-0']) }}>
    <ol class="breadcrumb">
        @foreach ($breadcrumbs as $route => $label)
            <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}" {{ $loop->last ? 'aria-current=page' : '' }}>
                @if ($loop->last)
                    {{ $label }}
                @else
                    <a data-cy="breadcrumb-{{ str_replace('.', '-', $route) }}"
                       href="{{ URL::isValidUrl($route) ? $route : route($route) }}">
                        {{ $label }}
                    </a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
