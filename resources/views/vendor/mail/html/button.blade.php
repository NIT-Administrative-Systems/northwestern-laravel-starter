@props(['url', 'color' => 'primary', 'align' => 'center'])
<table class="action"
       role="presentation"
       align="{{ $align }}"
       width="100%"
       cellpadding="0"
       cellspacing="0">
    <tr>
        <td align="{{ $align }}">
            <table role="presentation"
                   width="100%"
                   border="0"
                   cellpadding="0"
                   cellspacing="0">
                <tr>
                    <td align="{{ $align }}">
                        <table role="presentation"
                               border="0"
                               cellpadding="0"
                               cellspacing="0">
                            <tr>
                                <td>
                                    <a class="button button-{{ $color }}"
                                       href="{{ $url }}"
                                       target="_blank"
                                       rel="noopener">{{ $slot }}</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
