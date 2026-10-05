<x-mail::message>
# Client Secret Expiration Notice

Hello,

A client secret for **{{ config('api.auth_realm') }}** belonging to **{{ $user->full_name }}** (`{{ $user->username }}`) is nearing its expiration date. When it expires, the client can no longer get access tokens, and the access tokens it holds stop working.

## Client Details

- **API User:** {{ $user->username }}
- **Client:** {{ $client->name }}
- **Client ID:** `{{ $client->id }}`
- **Expiration Date:** {{ $expirationDate }}
- **Days Remaining:** {{ $daysUntilExpiration }} {{ Str::plural('day', $daysUntilExpiration) }}

@if($daysUntilExpiration <= 7)
**⚠️ Immediate Action Required:** This secret will expire shortly. Ask an administrator to rotate the client, then update the integration with the new client ID and secret before the expiration date.
@else
**Action Recommended:** Ask an administrator to rotate the client ahead of time. The current client keeps working until it is revoked or expires, so the integration can switch without interruption.
@endif

@if($client->last_used_at)
**Note:** This client was last used on {{ $client->last_used_at->format('F j, Y \a\t g:i A T') }}.
@endif

Thanks,<br>
{{ config('app.name') }}

<x-slot:subcopy>
This is an automated notification. If you believe you received this email in error, please contact an administrator.
</x-slot:subcopy>
</x-mail::message>
