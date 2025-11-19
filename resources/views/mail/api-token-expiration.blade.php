<x-mail::message>
# API Token Expiration Notice

Hello,

An API token for **{{ config('auth.api.auth_realm') }}** associated with **{{ $user->full_name }}** (`{{ $user->username }}`) is nearing its expiration date.

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

@if($token->usage_count > 0)
**Note:** This token has been used {{ number_format($token->usage_count) }} {{ Str::plural('time', $token->usage_count) }} and was last used on {{ $token->last_used_at?->format('F j, Y \a\t g:i A T') }}.
Please ensure you update all systems using this token before revoking it.
@endif

Thanks,<br>
{{ config('app.name') }}

<x-slot:subcopy>
This is an automated notification. If you believe you received this email in error, please contact an administrator.
</x-slot:subcopy>
</x-mail::message>
