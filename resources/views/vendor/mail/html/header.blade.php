@props(['url'])
<tr>
    <td class="header">
        {{-- Text, not the SVG wordmark: many email clients, including Gmail, don't render SVG. --}}
        <p class="header-eyebrow">Northwestern University</p>
        <a href="{{ $url }}" style="display: inline-block;">
            {{ $slot }}
        </a>
    </td>
</tr>
