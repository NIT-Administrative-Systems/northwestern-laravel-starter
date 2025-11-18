<x-mail::message>
# API Token Expiration Notice

Hello,

A Bearer token associated with **{{ $user->full_name }}** (`{{ $user->username }}`) is nearing its expiration date.

## Token Details

- **API User:** {{ $user->username }}
- **Token Prefix:** `{{ $token->token_prefix }}...`
- **Expiration Date:** {{ $expirationDate }}
- **Days Remaining:** {{ $daysUntilExpiration }} {{ Str::plural('day', $daysUntilExpiration) }}

@if($daysUntilExpiration <= 7)
**⚠️ Immediate Action Required:** This token will expire shortly. Please rotate the token before the expiration date to avoid service disruption.
@else
**Action Recommended:** Consider rotating this token ahead of time to prevent any service interruptions.
@endif

## Required Steps

1. Log in to **{{ config('app.name') }}**
2. Navigate to the API User's profile
3. Create a new token before the current one expires
4. Update any integration that uses the `Authorization: Bearer {token}` header with the new credential
5. Rotate the expiring token to prevent additional notifications

@if($token->usage_count > 0)
**Note:** This token has been used {{ number_format($token->usage_count) }} {{ Str::plural('time', $token->usage_count) }}. Please ensure you update all systems using this token before revoking it.
@endif

<x-mail::button :url="config('app.url')">
View API User Profile
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}

<x-slot:subcopy>
This is an automated notification. If you believe you received this email in error, please contact your administrator.
</x-slot:subcopy>
</x-mail::message>
