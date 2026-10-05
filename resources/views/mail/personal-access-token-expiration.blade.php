<x-mail::message>
# Personal Access Token Expiring

Hello {{ $user->first_name ?: $user->full_name }},

Your personal access token **{{ $token->name }}** for **{{ config('app.name') }}** expires on {{ $expirationDate }}, in {{ $daysUntilExpiration }} {{ Str::plural('day', $daysUntilExpiration) }}. Anything that uses it will stop working then.

To keep a script working, create a new token and update the script before the old one expires.

<x-mail::button :url="$accessTokensUrl">
Manage your access tokens
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}

<x-slot:subcopy>
You can turn these emails off on your [Preferences]({{ $preferencesUrl }}) page.
</x-slot:subcopy>
</x-mail::message>
