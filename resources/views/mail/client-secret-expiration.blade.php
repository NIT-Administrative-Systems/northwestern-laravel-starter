<x-mail::message>
# A service client secret is expiring

Hello,

The secret of **{{ $client->name }}**, a service client of **{{ $user->full_name }}** (`{{ $user->username }}`) in **{{ config('app.name') }}**, expires at {{ $expiresAt }}, in {{ $expiresIn }}. Then the service client can't get new access tokens, and the ones it holds stop working.

- **API user:** {{ $user->username }}
- **Service client:** {{ $client->name }}
- **Client ID:** `{{ $client->id }}`
- **Expires:** {{ $expiresAt }}

@if($daysUntilExpiration <= 7)
**Rotate this service client before {{ $expiresOn }}.** Ask an administrator to rotate it, then update the integration with the new client ID and secret.
@else
Ask an administrator to rotate this service client before {{ $expiresOn }}. It keeps working until it's revoked or expires, so the integration can switch to the replacement without interruption.
@endif

@if($lastUsedAt)
It was last used at {{ $lastUsedAt }}.
@endif

Thanks,<br>
{{ config('app.name') }}

<x-slot:subcopy>
You're receiving this as the contact for {{ $user->full_name }}.
</x-slot:subcopy>
</x-mail::message>
